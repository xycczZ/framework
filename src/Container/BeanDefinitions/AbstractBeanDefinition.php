<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use Error;
use JetBrains\PhpStorm\ExpectedValues;
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
use Xycc\Winter\Container\Exceptions\InvalidBindingException;
use Xycc\Winter\Container\Exceptions\MultiPrimaryException;
use Xycc\Winter\Container\Exceptions\NotFoundException;
use Xycc\Winter\Container\Exceptions\PriorityDecidedException;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Scope;
use Xycc\Winter\Contract\Container\BeanDefinitionContract;

abstract class AbstractBeanDefinition implements BeanDefinitionContract
{
    use ClassInfo, MethodInfo, PropInfo, ParamInfo, ParseMetadata;

    protected BeanDefinitionCollection $manager;

    protected bool $fromConfiguration = false;
    protected string $configurationId = '';
    protected string $configurationMethod = '';

    protected static array $semi = [];

    protected $instance = null;

    protected ?string $proxyClass = null;

    protected ?SplFileInfo $fileInfo = null;
    // bean 的名字
    protected ?string $name = null;

    public function getInstance(array $extra = [], AbstractBeanDefinition $parent = null, bool $hasType = true): mixed
    {
        switch ($this->scope) {
            case Scope::SCOPE_SINGLETON:
                if (!$this->instance) {
                    $id = $this->getId();
                    BeanDefinitionCollection::appendSemi($id);
                    $instance = $this->resolveInstance($extra);
                    BeanDefinitionCollection::popSemi();
                    $this->instance = $instance;
                }
                $instance = $this->instance;
                break;
            case Scope::SCOPE_SESSION:
                BeanDefinitionCollection::appendSemi($this->getId());
                if ($parent !== null && $this->scopeMode === Scope::MODE_PROXY) {
                    $instance = $this->createProxy($hasType);
                } elseif ($parent === null) {
                    if (!isset($this->instance[Coroutine::getContext()['fd']])) {
                        $instance = $this->resolveInstance($extra);
                        $this->instance[Coroutine::getContext()['fd']] = $instance;
                    }
                    $instance = $this->instance[Coroutine::getContext()['fd']];
                } else {
                    $instance = $this->resolveInstance($extra);
                }
                BeanDefinitionCollection::popSemi();
                break;
            case Scope::SCOPE_REQUEST:
                $id = $this->getId();
                BeanDefinitionCollection::appendSemi($id);
                if ($parent !== null && $this->scopeMode === Scope::MODE_PROXY) {
                    $instance = $this->createProxy($hasType);
                } elseif ($parent === null) {
                    if (!isset($this->instance[Coroutine::getCid()])) {
                        $instance = $this->resolveInstance($extra);
                        $this->instance[Coroutine::getCid()] = $instance;
                    } else {
                        $instance = $this->instance[Coroutine::getCid()];
                    }
                } else {
                    $instance = $this->resolveInstance($extra);
                }
                BeanDefinitionCollection::popSemi();
                break;
            case Scope::SCOPE_PROTOTYPE:
                $id = $this->getId();
                BeanDefinitionCollection::appendSemi($id);
                if ($parent !== null && $this->scopeMode === Scope::MODE_PROXY) {
                    $instance = $this->createProxy($hasType);
                } else {
                    $instance = $this->resolveInstance($extra);
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

    protected abstract function resolveInstance(array $extra = []);

    public function setInstance($instance): static
    {
        $this->instance = $instance;
        return $this;
    }

    public function clearSession(int $sessionId)
    {
        if ($this->isSession()) {
            if (isset($this->instance[$sessionId])) {
                unset($this->instance[$sessionId]);
            }
        }
    }

    public function clearRequest(int $requestId)
    {
        if ($this->isRequest()) {
            if (isset($this->instance[$requestId])) {
                unset($this->instance[$requestId]);
            }
        }
    }

    public function isConfiguration(): bool
    {
        return $this->isConfiguration;
    }

    public function isFromConfiguration(): bool
    {
        return $this->fromConfiguration;
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

    public function isLazyInit(): bool
    {
        return $this->lazyInit;
    }

    public function setLazyInit(bool $lazyInit): static
    {
        $this->lazyInit = $lazyInit;
        return $this;
    }

    #[ExpectedValues(flags: Scope::SCOPES)]
    public function getScope(): int
    {
        return $this->scope;
    }

    public function setScope(#[ExpectedValues(flags: Scope::SCOPES)] int $scope): static
    {
        $this->scope = $scope;
        return $this;
    }


    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function setPrimary(bool $isPrimary): static
    {
        $this->primary = $isPrimary;
        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): static
    {
        $this->order = $order;
        return $this;
    }

    #[ExpectedValues(flags: Scope::MODES)]
    public function getScopeMode(): int
    {
        return $this->scopeMode;
    }

    public function setScopeMode(#[ExpectedValues(flags: Scope::MODES)] int $scopeMode): static
    {
        $this->scopeMode = $scopeMode;
        return $this;
    }

    public function isPrototype(): bool
    {
        return $this->scope === Scope::SCOPE_PROTOTYPE;
    }

    public function isSingleton(): bool
    {
        return $this->scope === Scope::SCOPE_SINGLETON;
    }

    public function isSession(): bool
    {
        return $this->scope === Scope::SCOPE_SESSION;
    }

    public function isRequest(): bool
    {
        return $this->scope === Scope::SCOPE_REQUEST;
    }

    public function getName(): ?string
    {
        return $this->name;
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
        if ($this->instance) {
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
        return array_filter(
            $attributes,
            fn (ReflectionAttribute $attr) => $extends
                ? $this->isSameOrSubClassOf($attribute, $attr->getName())
                : $attribute === $attr->getName()
        );
    }

    #[Pure]
    public function isSameOrSubClassOf(string $parentClass, string $subClass = ''): bool
    {
        if (!$subClass) {
            $subClass = $this->className;
        }
        return $parentClass === $subClass || is_subclass_of($subClass, $parentClass);
    }

    public function getId(): ?string
    {
        return $this->name ?: $this->className;
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
                    $value = $this->manager->findHighestPriorityDefinitionByType($type->getName());
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
}