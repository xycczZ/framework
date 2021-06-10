<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;

use SplFileInfo;
use Xycc\Winter\Config\Config;
use Xycc\Winter\Container\{BeanDefinitions\ClassBeanDefinition, Factory\BeanFactory, Proxy\ProxyManager};
use Xycc\Winter\Contract\{Attributes\Bean, Bootstrap, Container\ContainerContract};


#[Bean('app')]
class Application implements ContainerContract
{
    protected BeanDefinitionCollection $beanDefinitions;

    protected array $boots = [];

    protected array $bootstraps = [];

    public static $app;

    protected string $rootPath;

    private Config $config;

    protected BeanFactory $beanFactory;

    public function __construct()
    {
        $this->beanDefinitions = new BeanDefinitionCollection();
        $this->beanFactory = new BeanFactory($this->beanDefinitions);
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    // string $id
    public function get($id, ?string $type = null)
    {
        return $this->beanFactory->get($id, $type);
    }

    public function getByName(string $name)
    {
        return $this->beanFactory->getByName($name);
    }

    public function getEnv()
    {
        return $_ENV['winter_app.env'];
    }

    public function getByType(string $class)
    {
        return $this->beanFactory->getByType($class);
    }

    public function has($id): bool
    {
        return $this->beanFactory->has($id);
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

        $this->scanConfig();

        // 扫描配置
        $this->clearProxy();
        $this->clearWeaves();
        $this->collectComponents();

        $this->bootstrap();
    }

    protected function addPredefinedComponents()
    {
        $app = new ClassBeanDefinition(self::class, new SplFileInfo(__FILE__), $this->beanDefinitions);
        $this->beanFactory->setPredefinedInstance('app', $app, $this);
        $this->beanDefinitions->add($app);

        $loader = new ClassLoader($this);
        $loaderDef = new ClassBeanDefinition(ClassLoader::class, new SplFileInfo(__DIR__ . '/ClassLoader.php'), $this->beanDefinitions);
        $this->beanFactory->setPredefinedInstance(ClassLoader::class, $loaderDef, $loader);
        $this->beanDefinitions->add($loaderDef);

        $proxyManager = new ProxyManager($this, $loader);
        $proxy = new ClassBeanDefinition(ProxyManager::class, new SplFileInfo(__DIR__ . '/Proxy/ProxyManager.php'), $this->beanDefinitions);
        $this->beanFactory->setPredefinedInstance(ProxyManager::class, $proxy, $proxyManager);
        $this->beanDefinitions->add($proxy);

        $definitions = new ClassBeanDefinition(BeanDefinitionCollection::class, new SplFileInfo(__DIR__ . '/BeanDefinitionCollection.php'), $this->beanDefinitions);
        $this->beanDefinitions->proxyManager = $proxyManager;
        $this->beanFactory->setPredefinedInstance('beanManager', $definitions, $this->beanDefinitions);
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
        // 先判断有没有这个类的定义解析了， 如果有直接更新， 没有的话才生成
        $def = $this->beanDefinitions->getDefByClass($class);
        if ($def === null) {
            $def = new ClassBeanDefinition($class, $file, $this->beanDefinitions);
            $this->beanDefinitions->add($def);
            if ($def->isBean()) {
                $this->beanFactory->addBean($def);
            }
            $defs = $def->setUpConfiguration();
            foreach ($defs as ['def' => $definition, 'method' => $method]) {
                $this->beanFactory->addBean($def, $method, $definition);
            }
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
        $this->beanFactory->start();
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

    public function getClassesByAttr(string $attr, bool $extends = false, bool $direct = false): array
    {
        return $this->beanDefinitions->getClassesByAttr($attr, $extends, $direct);
    }

    public function clearRequest()
    {
        $this->beanFactory->clearRequest();
    }

    public function clearSession()
    {
        $this->beanFactory->clearSession();
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

    public function execute($action, array $extra = [])
    {
        return $this->beanFactory->execute($action, $extra);
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