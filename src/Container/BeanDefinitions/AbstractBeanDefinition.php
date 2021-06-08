<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use Error;
use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use SplFileInfo;
use Swoole\Coroutine;
use Xycc\Winter\Config\Attributes\Value;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\Exceptions\DuplicatedIdentityException;
use Xycc\Winter\Container\Exceptions\InvalidBindingException;
use Xycc\Winter\Container\Exceptions\MultiPrimaryException;
use Xycc\Winter\Container\Exceptions\NotFoundException;
use Xycc\Winter\Container\Exceptions\PriorityDecidedException;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Contract\Attributes\Primary;
use Xycc\Winter\Contract\Attributes\Scope;
use Xycc\Winter\Contract\Container\BeanDefinitionContract;

abstract class AbstractBeanDefinition implements BeanDefinitionContract
{
    use ClassInfo, MethodInfo, PropInfo, ParamInfo, ParseMetadata;

    protected BeanDefinitionCollection $manager;

    protected static array $semi = [];

    protected $instance = null;

    protected ?string $proxyClass = null;

    protected ?SplFileInfo $fileInfo = null;

    // 一个类可能定义了多个不同名字来引用，可能是方法 bean 中，也可能是本类上直接注解
    // 实例化针对每一个名字处理， 名字对应的是方法还是本类 映射关系
    protected array $names = [];

    public function getInstance(array $extra = [], ?string $name = null, AbstractBeanDefinition $parent = null, bool $hasType = true): mixed
    {
        if (!isset($this->names[$name])) {
            throw new NotFoundException($name ?? $this->className);
        }
        $info = $this->names[$name];

        switch ($info['scope']) {
            case Scope::SCOPE_SINGLETON:
                if (!isset($this->instance[$name])) {
                    BeanDefinitionCollection::appendSemi($name ?? $this->className);
                    $instance = $this->resolveInstance($info, $extra);
                    BeanDefinitionCollection::popSemi();
                    $this->instance[$name] = $instance;
                }
                $instance = $this->instance[$name];
                break;
            case Scope::SCOPE_SESSION:
                BeanDefinitionCollection::appendSemi($name ?? $this->className);
                if ($parent !== null && $info['scopeMode'] === Scope::MODE_PROXY) {
                    $instance = $this->createProxy($hasType);
                } elseif ($parent === null) {
                    if (!isset($this->instance[$name][Coroutine::getContext()['fd']])) {
                        $instance = $this->resolveInstance($info, $extra);
                        $this->instance[$name][Coroutine::getContext()['fd']] = $instance;
                    }
                    $instance = $this->instance[$name][Coroutine::getContext()['fd']];
                } else {
                    $instance = $this->resolveInstance($info, $extra);
                }
                BeanDefinitionCollection::popSemi();
                break;
            case Scope::SCOPE_REQUEST:
                BeanDefinitionCollection::appendSemi($name ?? $this->className);
                if ($parent !== null && $info['scopeMode'] === Scope::MODE_PROXY) {
                    $instance = $this->createProxy($hasType);
                } elseif ($parent === null) {
                    if (!isset($this->instance[$name][Coroutine::getContext()['fd']])) {
                        $instance = $this->resolveInstance($info, $extra);
                        $this->instance[$name][Coroutine::getContext()['fd']] = $instance;
                    } else {
                        $instance = $this->instance[$name][Coroutine::getContext()['fd']];
                    }
                } else {
                    $instance = $this->resolveInstance($info, $extra);
                }
                BeanDefinitionCollection::popSemi();
                break;
            case Scope::SCOPE_PROTOTYPE:
                BeanDefinitionCollection::appendSemi($name ?? $this->className);
                if ($parent !== null && $info['scopeMode'] === Scope::MODE_PROXY) {
                    $instance = $this->createProxy($hasType);
                } else {
                    $instance = $this->resolveInstance($info, $extra);
                }
                BeanDefinitionCollection::popSemi();
                break;
            default:
                throw new Error('未知作用域');
        }

        $this->inject($instance);
        return $instance;
    }

    protected function createProxy(bool $hasType): object
    {
        if ($this->proxyClass !== null) {
            return new $this->proxyClass;
        }
        // 如果有文件，且类不是 final 类，生成一个代理类
        if (!$this->refClass->isFinal() && $this->fileInfo !== null) {
            $instance = $this->manager->proxyManager->generate($this, true);
            $this->proxyClass = $instance::class;
            return $instance;
        }
        // 否则判断当前是否有类型标识，如果没有类型标识， 可以生成一个代理类
        if (!$hasType) {
            $instance = $this->manager->proxyManager->generate($this, false);
            $this->proxyClass = $instance::class;
            return $instance;
        }
        // 如果有类型标识，且没有文件可以生成，抛出异常
        throw new InvalidBindingException('参数注入的依赖，#[Lazy]或者代理模式的非 Singleton bean， 参数的类型必须是可以继承的类型或者无标注类型');
    }

    protected abstract function resolveInstance(array $info, array $extra = []);

    protected function invokeConfiguration(array $info, array $extra = [])
    {
        $def = $this->manager->findDefinitionByClass($info['configurationClass']);
        return $this->invokeMethod($def->getInstance(), $info['configurationMethod'], $extra);
    }

    public function setInstance($instance): static
    {
        $this->instance = $instance;
        return $this;
    }

    public function clearSession(int $sessionId, ?string $name)
    {
        if (isset($this->instance[$name][$sessionId])) {
            unset($this->instance[$name][$sessionId]);
        }
    }

    public function clearRequest(int $requestId, ?string $name)
    {
        if (isset($this->instance[$name][$requestId])) {
            unset($this->instance[$name][$requestId]);
        }
    }

    public function isConfiguration(): bool
    {
        return $this->isConfiguration;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @return ReflectionMethod[]
     */
    public function getSetters(): array
    {
        return $this->setters;
    }

    public function getConfigurationMethods(): array
    {
        return $this->configurationMethods;
    }

    public function haveConfigurationMethods(): bool
    {
        return count($this->configurationMethods) > 0;
    }

    public function getNames(): array
    {
        return $this->names;
    }

    public function getFile(): ?SplFileInfo
    {
        return $this->fileInfo;
    }

    public function reload(SplFileInfo $fileInfo, string $className)
    {
        $this->fileInfo = $fileInfo;
        $this->className = $className;
        $this->refClass = new ReflectionClass($className);
        $this->parseMetadata($this->refClass);
        // if have instance, recreate instance
        if ($this->instance) { // todo
            $this->instance = null;
        }
    }

    public function getRefClass(): ReflectionClass
    {
        return $this->refClass;
    }

    public function getRefMethod(string $method = ''): array|null|ReflectionMethod
    {
        if ($method) {
            return $this->refMethods[$method] ?? null;
        }
        return $this->refMethods;
    }

    public function getRefProp(string $prop = ''): array|null|ReflectionProperty
    {
        if ($prop) {
            return $this->refProps[$prop] ?? null;
        }
        return $this->refProps;
    }

    public function getRefParams(string $method, int|string|null $paramNameOrIndex = null): array|null|ReflectionParameter
    {
        if ($paramNameOrIndex === null) {
            return $this->refParams[$method] ?? null;
        }

        $name = $this->convertPositionToName($method, $paramNameOrIndex);
        return $this->refParams[$method][$name] ?? null;
    }

    protected function filterAttribute(array $attributes, string $attribute, bool $extends = false): array
    {
        return array_values(array_filter(
            $attributes,
            fn (ReflectionAttribute $attr) => $extends
                ? $this->isSameOrSubClassOf($attribute, $attr->getName())
                : $attribute === $attr->getName()
        ));
    }

    #[Pure]
    public function isSameOrSubClassOf(string $parentClass, string $subClass = ''): bool
    {
        if (!$subClass) {
            $subClass = $this->className;
        }
        return $parentClass === $subClass || is_subclass_of($subClass, $parentClass);
    }

    /**
     * @param ReflectionParameter[] $params
     */
    protected function getMethodArgs(array $params, array $extra = []): array
    {
        $args = [];

        foreach ($params as $param) {
            $arg = $this->handlePredefinedAttribute($param);
            if ($arg !== null) {
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
            $args[] = $this->getParamInstance($param);
        }

        return $args;
    }

    protected function getParamInstance(ReflectionParameter $param)
    {
        if (!$param->hasType()) {
            throw new NotFoundException(message: sprintf('参数 %s::%s(%s) 没有类型提示并且没有 #[Autowired]注解， 不能被注入', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
        }
        $type = $param->getType();
        $this->assertNotUnionType($type);
        $class = $type->getName();
        $definitions = $this->manager->findDefinitionsByType($class);

        if (count($definitions) === 1) {
            return $definitions[0]->getInstance(parent: $this, hasType: $param->hasType());
        } elseif (count($definitions) === 0) {
            throw new NotFoundException(message: sprintf('参数 %s::%s(%s) 没有类型提示并且没有 #[Autowired]注解， 不能被注入', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
        }

        $definition = $this->getHighestPriorityDefinition($definitions);
        return $definition->getInstance(parent: $this, hasType: $param->hasType());
    }

    protected function getHighestPriorityDefinition(array $definitions): AbstractBeanDefinition
    {
        if (count($definitions) === 1) {
            return $definitions[0];
        }

        $primaries = array_filter($definitions, fn (AbstractBeanDefinition $definition) => $definition->isPrimary());
        if (count($primaries) === 1) {
            return $primaries[0];
        } elseif (count($primaries) > 1) {
            throw new MultiPrimaryException(sprintf('multi #[Primary] beans with same class: %s', implode(',', array_map(fn (AbstractBeanDefinition $definition) => $definition->getClassName(), $primaries))));
        }

        usort($definitions, fn (AbstractBeanDefinition $a, AbstractBeanDefinition $b) => $a->order <=> $b->order);
        $definitions = array_values($definitions);
        if ($definitions[0]->order === $definitions[1]->order) {
            $eq = array_map(fn (AbstractBeanDefinition $def) => $def->getClassName(),
                array_filter($definitions, fn (AbstractBeanDefinition $def) => $def->order === $definitions[0]->order));
            throw new PriorityDecidedException('same priority bean: ' . implode(', ', $eq));
        }

        return $definitions[0];
    }

    protected function assertNotUnionType(ReflectionType $type)
    {
        if ($type instanceof ReflectionUnionType) {
            throw new InvalidBindingException('The types of beans or autowired objects could not be union type');
        }
    }

    protected function handlePredefinedAttribute(ReflectionParameter $param)
    {
        if ($configAttr = $param->getAttributes(Value::class)) {
            $config = $this->manager->findDefinitionByName('config')->getInstance();
            return $config->get($configAttr[0]->newInstance()->path);
        } elseif (count($param->getAttributes(Lazy::class)) > 0) {
            if ($param->hasType()) {
                $type = $param->getType();
                $this->assertNotUnionType($type);
                $def = $this->manager->findHighestPriorityDefinitionByType($type->getName());
                return $def->createProxy(true);
            } else {
                // 参数没有类型的话，必须同时还有autowired注解
                $autowiredAttr = $param->getAttributes(Autowired::class);
                if (count($autowiredAttr) === 0) {
                    throw new NotFoundException('Lazy修饰的无类型标注的参数必须有带名字的Autowired注解');
                }
                $autowired = $autowiredAttr[0]->newInstance();
                if ($autowired->value === null) {
                    throw new NotFoundException('Lazy修饰的无类型标注的参数必须有带名字的Autowired注解');
                }
                $instance = $this->manager->findDefinitionByName($autowired->value)?->createProxy(false);
                if ($instance !== null || $autowired->required === false) {
                    return $instance;
                }
                throw new NotFoundException('依赖未找到: ' . $autowired->value);
            }
        } elseif ($autowiredAttr = $param->getAttributes(Autowired::class)) {
            // 如果参数的 autowired 注解没有名字， 跟后面按照类型注入一个流程
            $autowired = $autowiredAttr[0]->newInstance();
            if ($autowired->value !== null) {
                $instance = $this->manager->findDefinitionByName($autowired->value)?->getInstance(parent: $this, hasType: $param->hasType());
                if ($instance !== null || $autowired->required === false) {
                    return $instance;
                } else {
                    throw new NotFoundException('依赖未找到: ' . $autowired->value);
                }
            }
        }
        return null;
    }

    public function inject($instance)
    {
        $this->invokeSetters($instance);
        // 然后注入#[Value], #[Autowired]
        $this->injectProps($instance);
    }

    protected function invokeSetters($instance)
    {
        $setters = $this->getSetters();
        foreach ($setters as $setter) {
            $this->invokeMethod($instance, $setter);
        }
    }

    protected function injectProps($instance)
    {
        foreach ($this->refProps as $refProp) {
            $refProp->setAccessible(true);
            if ($refProp->isInitialized($instance)) {
                continue;
            }
            $value = $this->getPropAttrs($refProp->name, Value::class);
            if (count($value) > 0) {
                $config = $this->manager->findDefinitionById('config')->getInstance();
                $path = $value[0]->newInstance()->path;
                if ($config->has($path)) {
                    $refProp->setValue($instance, $config->get($path));
                } else {
                    // 如果不存在这个配置项，检查有无默认值，有默认值用默认值, 没有默认值抛异常
                    if (!$refProp->hasDefaultValue()) {
                        throw new RuntimeException(sprintf('config: %s not found', $path));
                    }
                }
            }

            $autowired = $this->getPropAttrs($refProp->name, Autowired::class);
            if (count($autowired) > 0) {
                // 属性autowired注入， 有name取name， 没有取类型
                $name = $autowired[0]->newInstance()->value;
                if ($name === null) {
                    $type = $refProp->getType();
                    if ($type === null) {
                        throw new InvalidBindingException('Autowired注解必须有name或者type');
                    }
                    dump('xxx', $type->getName());
                    $value = $this->manager->findHighestPriorityDefinitionByType($type->getName());
                    dump($value->isBean(), $value->className, $value->getId(), $value::class);
                    $value = $value->getInstance(parent: $this);
                } else {
                    $value = $this->manager->findDefinitionByName($name)->getInstance(parent: $this, hasType: $refProp->hasType());
                }
                $refProp->setValue($instance, $value);
            }
        }
    }

    public function invokeMethod(object $instance, ReflectionMethod|string $method, array $extra = [])
    {
        if (is_string($method)) {
            $refClass = new ReflectionClass($instance);
            $method = $refClass->getMethod($method);
        }

        $args = $this->getMethodArgs($method->getParameters(), $extra);
        return $method->invokeArgs($instance, $args);
    }

    /// 更新
    /// 每个类的 bean只可能有一个是没有名字的, 这个没有名字的 bean就是类本身.
    /// 其他的都必须有名字， 方法 bean 如果没有给名字， 方法名本身就作为名字
    /// 每个类信息只可能有一个是类本身上注解 Bean 的
    /// 其他的必须是方法 Bean
    /// 所以 names 只是所有的方法 bean 的信息
    /// 本类的信息就存在 BeanDefinition 上
    /// 如果一个方法 Bean 返回了一个标注了 Configuration 的类， 这个方法会被忽略掉
    /// 从 Configuration 获取方法 bean 的时候， 总是使用类型获取 Configuration 的实例
    public function update(ReflectionMethod $method, ?string $name = null)
    {
        $name = $name ?: $method->name;

        if (isset($this->names[$name])) {
            throw new DuplicatedIdentityException($this->className, [$name]);
        }

        $this->manager->addName($name, $this);

        // 更新方法， 在生成一个 ClassBeanDefinition 对象之前，无论是先扫描到的是方法 bean
        // 还是先扫描到的类， 都会先通过类加载器获取到文件路径，并且通过反射获取到类的信息
        $this->names[$name] = $this->updateMethod($method);
    }

    protected function updateMethod(ReflectionMethod $method)
    {
        $def = $this->manager->findDefinitionByClass($method->class);
        $scope = (current($def->getMethodAttributes($method->name, Scope::class)) ?: null)?->newInstance();
        $order = current($def->getMethodAttributes($method->name, Order::class)) ?: null;
        return [
            'instance' => null,
            'configurationClass' => $method->class,
            'configurationMethod' => $method->name,
            'fromConfiguration' => true,
            'scope' => $scope?->scope,
            'scopeMode' => $scope?->scopeMode,
            'order' => $order?->newInstance()?->vlaue,
            'primary' => $def->methodHasAttribute($method->name, Primary::class),
            'lazy' => $this->methodHasAttribute($method->name, Lazy::class),
        ];
    }
}