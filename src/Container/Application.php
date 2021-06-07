<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;

use Closure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use RuntimeException;
use SplFileInfo;
use Swoole\Coroutine;
use Xycc\Winter\Config\Config;
use Xycc\Winter\Container\{BeanDefinitions\AbstractBeanDefinition,
    BeanDefinitions\ClassBeanDefinition,
    Exceptions\NotFoundException,
    Proxy\ProxyManager
};
use Xycc\Winter\Contract\{Attributes\Autowired, Attributes\Bean, Bootstrap, Container\ContainerContract};


#[Bean('app')]
class Application implements ContainerContract
{
    protected BeanDefinitionCollection $beanDefinitions;

    protected array $boots = [];

    protected array $bootstraps = [];

    public static $app;

    protected string $rootPath;

    private Config $config;

    public function __construct()
    {
        $this->beanDefinitions = new BeanDefinitionCollection();
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    // string $id
    public function get($id, ?string $type = null, int $mode = Autowired::AUTO, bool $required = true, array $extra = [])
    {
        return match ($mode) {
            Autowired::AUTO => $this->getByName($id, extra: $extra) ?? $this->getByType($type ?? $id, extra: $extra),
            Autowired::BY_NAME => $this->getByName($id, extra: $extra),
            Autowired::BY_TYPE => $this->getByType($type ?? $id, extra: $extra),
        };
    }

    public function getByName(?string $name, array $extra = [])
    {
        if (!$name) {
            return null;
        }

        $def = $this->beanDefinitions->findDefinitionByName($name);
        return $def?->getInstance($extra);
    }

    public function getEnv()
    {
        return $_ENV['winter_app.env'];
    }

    public function getByType(string $class, array $extra = [])
    {
        $def = $this->beanDefinitions->findHighestPriorityDefinitionByType($class);
        return $def->getInstance($extra);
    }

    public function has($id): bool
    {
        return count($this->beanDefinitions->filterDefinitions(fn ($def) => $def->getId() === $id)) > 0;
    }

    public function getPath(string $path = ''): string
    {
        return $this->rootPath . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    public function start(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/');

        static::$app = $this;

        $this->addPredefinedComponents();

        $this->scanPath();

        // 扫描配置
        $this->scanConfig();
        $this->clearProxy();
        $this->clearWeaves();
        $this->collectComponents();

        $this->bootstrap();
    }

    protected function addPredefinedComponents()
    {
        $app = new ClassBeanDefinition(static::class, new SplFileInfo(__FILE__), $this->beanDefinitions);
        $app->setInstance($this);
        $this->beanDefinitions->add($app);

        $loader = new ClassLoader($this);
        $loaderDef = new ClassBeanDefinition(ClassLoader::class, new SplFileInfo(__DIR__ . '/ClassLoader.php'), $this->beanDefinitions);
        $loaderDef->setInstance($loader);
        $this->beanDefinitions->add($loaderDef);

        $proxyManager = new ProxyManager($this, $loader);
        $proxy = new ClassBeanDefinition(ProxyManager::class, new SplFileInfo(__DIR__ . '/Proxy/ProxyManager.php'), $this->beanDefinitions);
        $proxy->setInstance($proxyManager);
        $this->beanDefinitions->add($proxy);

        $definitions = new ClassBeanDefinition(BeanDefinitionCollection::class, new SplFileInfo(__DIR__ . '/BeanDefinitionCollection.php'), $this->beanDefinitions);
        $definitions->setInstance($this->beanDefinitions);
        $this->beanDefinitions->proxyManager = $proxyManager;
        $this->beanDefinitions->add($definitions);
    }

    /**
     * 1. 扫描 bootstrap 的 scan 方法指定的路径, 收集注解信息
     * 2. 还需要扫描配置文件的信息
     * 3. 扫描的文件中还需要解析出来工厂方法
     */
    protected function scanPath(): void
    {
        foreach ($this->getBoots() as $boot) {
            /**@var Bootstrap $boot */
            $pairs = $boot::scanPath();
            $exclude = $boot::exclude();
            foreach ($pairs as $directory => $ns) {
                $files = FileIterator::getFiles($directory, $ns, $exclude);
                foreach ($files as $file) {
                    $this->parseFile($file);
                }
            }
        }
    }

    protected function parseFile(SplFileInfo $file)
    {
        $class = FileIterator::getClassName($file);
        $ref = new ReflectionClass($class);
        if (count($ref->getAttributes(Bean::class, ReflectionAttribute::IS_INSTANCEOF)) > 0) {
            $beanDefinition = new ClassBeanDefinition($class, $file, $this->beanDefinitions);
            $this->beanDefinitions->add($beanDefinition);
        }
    }

    protected function scanConfig()
    {
        $this->config = $config = $this->getByType(Config::class);
        $config->set('app.root', $this->rootPath);
        $config->set('app.runtime', $this->rootPath . '/runtime');
        $config->scan($this->rootPath);
    }

    protected function bootstrap()
    {
        $this->bootstraps = array_map(fn ($boot) => new $boot, $this->getBoots());
        foreach ($this->bootstraps as $bootstrap) {
            $bootstrap->boot($this);
        }
    }

    public function appendBoots(string ...$classes)
    {
        foreach ($classes as $class) {
            if (! in_array($class, $this->boots)) {
                $this->boots[] = $class;
            }
        }
    }

    public function getBoots(): array
    {
        $boots = [];
        if (file_exists($path = $this->getPath('/runtime/bootstraps.php'))) {
            $boots = require $path;
        }
        return array_merge($boots, $this->boots);
    }

    /**
     * 收集单例的，非延迟实例化的，非方法的 bean
     * 只有在初始化容器的时候才调用此方法
     */
    protected function collectComponents()
    {
        $definitions = $this->beanDefinitions->filterDefinitions(fn (AbstractBeanDefinition $definition) => $definition->isSingleton() && !$definition->isLazyInit());

        foreach ($definitions as $definition) {
            $definition->getInstance();
        }
    }

    public function publishFiles(string $filePath, string $toPath = ''): bool
    {
        $configPath = $this->rootPath . '/config';
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }

        $fileName = basename($filePath);

        if (! $toPath) {
            $toPath = $configPath . '/' . $fileName;
        }

        $extension = '';
        if (str_contains($fileName, '.')) {
            $extensions = explode('.', $fileName);
            $extension = $extensions[count($extensions) - 1];
            array_pop($extensions);
            $fileName = implode('.',$extensions);
        }

        $extension = $extension === '' ? '' : '.*';

        $pattern = $configPath . '/' . $fileName . $extension;
        $files = glob($pattern);

        if (!$files) {
            return copy($filePath, $toPath);
        }

        return false;
    }

    public function getClassesByAttr(string $attr, bool $extends = false): array
    {
        return $this->beanDefinitions->getClassesByAttr($attr, $extends);
    }

    public function getMethodsByAttr(string $class, string $attr, bool $extends = false): array
    {
        return $this->beanDefinitions->getMethodsByAttr($class, $attr, $extends);
    }

    public function getPropsByAttr(string $class, string $attr, bool $extends = false): array
    {
        return $this->beanDefinitions->getPropsByAttr($class, $attr, $extends);
    }

    public function getParamsByAttr(string $class, string $method, string $attr, bool $extends = false): array
    {
        return $this->beanDefinitions->getParamsByAttr($class, $method, $attr, $extends);
    }

    public function clearRequest()
    {
        $this->beanDefinitions->clearRequest(Coroutine::getCid());
    }

    public function clearSession(int $id)
    {
        $this->beanDefinitions->clearSession($id);
    }

    public static function postAutoloadDump($event)
    {
        $extra = $event->getComposer()->getPackage()->getExtra();

        $discover = [];
        if (isset($extra['discover'])) {
            $discover = $extra['discover'];
        }

        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $installedJson = json_decode(file_get_contents($vendorDir . '/composer/installed.json'), true);

        foreach ($installedJson['packages'] as $package) {
            if (isset($package['extra']['discover'])) {
                $discover = array_merge($discover, $package['extra']['discover']);
            }
        }

        $discover = array_unique($discover);
        $discover = array_filter($discover, fn (string $class) => is_subclass_of($class, Bootstrap::class));

        $path = $vendorDir . '/../runtime/bootstraps.php';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $content = implode(",\n    ", array_map(fn ($file) => "'$file'", $discover));
        file_put_contents($path, <<<HERE
<?php

return [
    $content
]
HERE
        );
        file_put_contents($path, ';', FILE_APPEND);
    }

    /**
     * @param ReflectionParameter[] $params
     */
    protected function getMethodArgs(array $params, array $extra = [])
    {
        $args = [];

        foreach ($params as $param) {
            $autowired = $param->getAttributes(Autowired::class);
            if (count($autowired) > 0) {
                $name = $autowired[0]->newInstance()->value;
                if ($name === null) {
                    $type = $param->getType();
                    if ($type === null) {
                        throw new NotFoundException(message: sprintf('autowired注解需要名字或者类型标注: %s::%s(%s)', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
                    }
                    $this->assertNotUnionType($type);
                    $arg = $this->beanDefinitions->findHighestPriorityDefinitionByType($type->getName())->getInstance();
                } else {
                    $arg = $this->beanDefinitions->findDefinitionByName($name)->getInstance();
                }
                $args[] = $arg;
                continue;
            }

            if (isset($extra[$param->name])) {
                if ($param->hasType()) {
                    $type = $param->getType();
                    $this->assertNotUnionType($type);
                    $args[] = convert_extra_type($type->getName(), $extra[$param->name]);
                } else {
                    $args[] = $extra[$param->name];
                }
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // 按类型注入
            if ($param->hasType()) {
                $type = $param->getType();
                $this->assertNotUnionType($type);
                $args[] = $this->beanDefinitions->findHighestPriorityDefinitionByType($type->getName())->getInstance();
            }
        }

        return $args;
    }

    private function assertNotUnionType(ReflectionType $type)
    {
        if (!($type instanceof ReflectionNamedType)) {
            throw new RuntimeException('只能注入命名类型');
        }
    }

    protected function executeAction(string $class, string $method, array $extra = [])
    {
        $def = $this->beanDefinitions->findHighestPriorityDefinitionByType($class);
        return $def->invokeMethod($def->getInstance(), $method, $extra);
    }

    protected function executeClosure(callable $handler, array $extra = [])
    {
        $ref = new ReflectionFunction($handler);
        $args = $this->getMethodArgs($ref->getParameters(), $extra);
        return $handler(...$args);
    }

    protected function executeActionObject($object, string $method, array $extra = [])
    {
        $refMethod = new ReflectionMethod($object, $method);
        $args = $this->getMethodArgs($refMethod->getParameters(), $extra);
        return $object->{$method}(...$args);
    }

    public function execute($action, array $extra = [])
    {
        if ($action instanceof Closure) {
            return $this->executeClosure($action, $extra);
        } elseif (is_array($action)) {
            if (is_string($action[0])) {
                return $this->executeAction($action[0], $action[1], $extra);
            }
            return $this->executeActionObject($action[0], $action[1], $extra);
        }
        throw new RuntimeException('需要传入函数或者数组形式的回调函数');
    }

    public function clearProxy()
    {
        foreach (glob($this->config->get('app.runtime') . '/proxy/*proxy*.php') as $item) {
            unlink($item);
        }
    }

    public function clearWeaves()
    {
        foreach (glob($this->config->get('app.runtime') . '/weaving/*.php') as $item) {
            unlink($item);
        }
    }
}