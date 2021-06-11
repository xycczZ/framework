<?php


namespace Xycc\Winter\Container\Factory;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Xycc\Winter\Config\Attributes\Value;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\Components\AttributeParser;
use Xycc\Winter\Container\Exceptions\CycleDependencyException;
use Xycc\Winter\Container\Exceptions\InvalidBindingException;
use Xycc\Winter\Container\Exceptions\MultiPrimaryException;
use Xycc\Winter\Container\Exceptions\NotFoundException;
use Xycc\Winter\Container\Proxy\LazyObject;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Required;
use Xycc\Winter\Contract\Attributes\Scope;

#[Bean]
class BeanFactory
{
    use CollectBeanInfo, SearchBeanInfo;

    /**
     * @var BeanInfo[]
     */
    protected array $beans = [];

    private static array $dependencyNames = [];

    public function __construct(
        protected BeanDefinitionCollection $manager
    )
    {
    }

    public function get(string $name, ?string $type = null, ?BeanInfo $parent = null)
    {
        if ($type) {
            $info = $this->searchByName($name);
        } else {
            $info = $this->searchByName($name) ?? $this->searchHighestByType($name);
        }

        if ($info === null) {
            throw new NotFoundException($name);
        }

        $instance = $this->resolveInstance($info, $parent);
        if ($type && !($instance instanceof $type)) {
            throw new InvalidBindingException(sprintf(
                'Bean type miss match. Found: %s, expected: %s', $instance::class, $type));
        }

        return $instance;
    }

    public function getByName(string $name, ?BeanInfo $parent = null)
    {
        if (!isset($this->beans[$name])) {
            throw new NotFoundException($name);
        }

        $info = $this->beans[$name];
        return $this->resolveInstance($info, $parent);
    }

    public function getByType(string $class, ?BeanInfo $parent = null)
    {
        $info = $this->searchHighestByType($class);
        if ($info !== null) {
            return $this->resolveInstance($info, $parent);
        }
        throw new NotFoundException($class);
    }

    /**
     * First look up by name. If not found， search by type.
     * If only one is found, return the only one.
     * If more than one is found, return the primary.
     * If there are multiple eligible types, find the dependency with the same name
     */
    protected function autowired(?string $name, int $mode, ReflectionProperty|ReflectionParameter $ref, ?BeanInfo $parent = null)
    {
        $instance = match ($mode) {
            Autowired::AUTO => $this->autowiredWithName($name, $ref, $parent) ?? $this->autowiredWithType($name, $ref, $parent),
            Autowired::BY_NAME => $this->autowiredWithName($name, $ref, $parent),
            Autowired::BY_TYPE => $this->autowiredWithType($name, $ref, $parent),
            default => null,
        };

        if ($instance === null) {
            throw new NotFoundException($name ?? ($ref->hasType() ? $ref->getType()->getName() : $ref->name));
        }

        return $instance;
    }

    protected function autowiredWithName(?string $name, ReflectionParameter|ReflectionProperty $ref, ?Beaninfo $parent)
    {
        if (isset($this->beans[$name])) {
            return $this->resolveInstance($this->beans[$name], $parent);
        }

        if (!$ref->hasType()) {
            return $this->getByName($ref->name, $parent);
        }

        return null;
    }

    protected function autowiredWithType(?string $name, ReflectionParameter|ReflectionProperty $ref, ?BeanInfo $parent)
    {
        $refType = $ref->getType();
        if ($refType instanceof ReflectionUnionType) {
            throw new RuntimeException('cannot inject union types, name: ' . $name . ', type: ' . $refType);
        }

        $infos = $this->searchByType($refType->getName(), true);

        if (count($infos) === 1) {
            return $this->resolveInstance(current($infos), $parent);
        } elseif (count($infos) === 0) {
            throw new NotFoundException($name ?: $ref->getType()->getName());
        }

        $primary = array_filter($infos, fn (BeanInfo $info) => $info->isPrimary());
        if (count($primary) === 1) {
            return $this->resolveInstance(current($primary), $parent);
        } elseif (count($primary) > 1) {
            throw new MultiPrimaryException(sprintf('Too many #[Primary]: %s', implode(', ', array_map(fn (BeanInfo $info) => $info->getName(), $primary))));
        }

        $fitNames = array_filter($infos, fn (BeanInfo $info) => $info->getName() === ($name ?: $ref->name));
        if (count($fitNames) === 1) {
            return $this->resolveInstance(current($fitNames), $parent);
        }

        throw new InvalidBindingException(sprintf('Cannot determine the unique bean, names: %s', implode(', ', array_map(fn (BeanInfo $info) => $info->getName(), $fitNames))));
    }

    public function has(string $name)
    {
        return isset($this->beans[$name]);
    }

    protected function resolveInstance(BeanInfo $info, ?BeanInfo $parent = null)
    {
        switch ($info->getScope()) {
            case Scope::SCOPE_SINGLETON:
                $instance = $info->getInstance();
                if ($instance !== null) {
                    return $instance;
                }
                $this->checkCycleDependency($info->getName());
                $instance = $this->createInstance($info);
                break;
            case Scope::SCOPE_SESSION:
            case Scope::SCOPE_REQUEST:
                // 如果有宿主类型存在，则直接根据代理类型，生成受管理的bean、受宿主管理的bean
                // 如果宿主类型不存在， 检查缓存
                if ($parent && $info->getScopeMode() === Scope::MODE_PROXY) {
                    return $this->createProxy($info, !!$info->getDef()->getClassName());
                } elseif ($parent) {
                    $this->checkCycleDependency($info->getName());
                    $instance = $this->createInstance($info);
                } else {
                    $instance = $info->getInstance();
                    if ($instance !== null) {
                        return $instance;
                    }
                    $this->checkCycleDependency($info->getName());
                    $instance = $this->createInstance($info);
                    $info->setInstance($instance);
                }
                break;
            case Scope::SCOPE_PROTOTYPE:
                if ($parent && $info->getScopeMode() === Scope::MODE_PROXY) {
                    return $this->createProxy($info, !!$info->getDef()->getClassName());
                } else {
                    $this->checkCycleDependency($info->getName());
                    $instance = $this->createInstance($info);
                }
                break;
            default:
                throw new RuntimeException('Unreachable: BeanFactory::resolveInstance=>default');
        }

        array_pop(self::$dependencyNames);

        $this->inject($instance, $info);
        $info->setInstance($instance);
        return $instance;
    }

    protected function createInstance(BeanInfo $info)
    {
        if ($info->isFromConf()) {
            $confName = $info->getConfName();
            if (!isset($this->beans[$confName])) {
                throw new NotFoundException($confName);
            }
            $conf = $this->resolveInstance($this->beans[$confName]);
            return $this->execute([$conf, $info->getConfMethod()]);
        }

        return $this->invokeConstructor($info);
    }

    protected function invokeConstructor(BeanInfo $info)
    {
        $class = $info->getDef()->getRefClass();
        $constructor = $class->getConstructor();
        if ($constructor === null) {
            return $class->newInstanceWithoutConstructor();
        }

        $params = $constructor->getParameters();
        if (count($params) === 0) {
            return $class->newInstance();
        }

        $args = $this->getMethodArgs($info, $params, $params);
        return $class->newInstanceArgs($args);
    }

    /**
     * @param ReflectionParameter[] $params
     */
    private function getMethodArgs(?BeanInfo $parent, array $params, array $extra = [])
    {
        $args = [];
        foreach ($params as $param) {
            $required = AttributeParser::getAttribute($param->getAttributes(), Required::class)?->newInstance()?->required ?: true;
            $arg = $this->handlePredefinedAttributes($parent, $param, $required);
            if ($arg !== null) {
                $args[] = $arg;
                continue;
            }

            if (isset($extra[$param->name])) {
                if ($param->hasType()) {
                    $type = $param->getType();
                    $type = $this->getRefType($type);
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

            if ($param->hasType()) {
                $type = $this->getRefType($param->getType());
                $info = $this->searchHighestByType($type);
                if ($info === null) {
                    if (!$required && $param->allowsNull()) {
                        $arg = null;
                    } elseif ($param->allowsNull()) {
                        throw new NotFoundException(sprintf('%s::%s(%s) 未找到依赖，尝试使用Autowired注解给予名字', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
                    }
                } else {
                    $arg = $this->resolveInstance($info, $parent);
                }
                $args[] = $arg;
                continue;
            }

            throw new NotFoundException(sprintf('%s::%s(%s) 未找到依赖，尝试标注类型或者使用Autowired注解', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
        }

        return $args;
    }

    /**
     * 处理几个预定义的注解
     * 1.
     */
    private function handlePredefinedAttributes(?BeanInfo $parent, ReflectionParameter|ReflectionProperty $param, bool $required = true)
    {
        $paramAttrs = $param->getAttributes();
        if ($valueAttr = AttributeParser::getAttribute($paramAttrs, Value::class)) {
            return $this->handleValueAttr($valueAttr->newInstance(), $required);
        } elseif (AttributeParser::getAttribute($paramAttrs, Lazy::class)) {
            return $this->handleLazyAttr($param);
        } elseif ($autowiredAttr = AttributeParser::getAttribute($paramAttrs, Autowired::class)) {
            return $this->handleAutowiredAttr($autowiredAttr->newInstance(), $param, $parent, $required);
        }
        return null;
    }

    private function handleValueAttr(Value $value, bool $required = true)
    {
        $config = $this->getByName('config');
        $path = $value->path;
        if ($config->has($path)) {
            return $config->get($path);
        }
        if ($required) {
            throw new RuntimeException(sprintf('required config `%s` not found', $path));
        }
        return null;
    }

    // required prop
    private function handleLazyAttr(ReflectionParameter|ReflectionProperty $param)
    {
        // lazy 如果有autowired注解， 按照autowired注解获取BeanInfo， 否则按照类型获取
        $autowiredAttr = AttributeParser::getAttribute($param->getAttributes(), Autowired::class);
        $type = $param->getType();
        $type = $this->getRefType($type);
        if ($autowiredAttr === null) {
            // 如果没有 autowired 注解， 根据类型注入
            if ($type === null) {
                throw new NotFoundException(message: 'Lazy 注解的参数|属性必须有类型 或者 同时有 Autowired 注解');
            }
            $name = $type->getName();
        } else {
            $beanName = $autowiredAttr->newInstance()->value;
            $name = $beanName ?: $type->getName();
            $name = $name ?: $param->name;
        }
        $info = $this->searchByName($name);
        if ($info === null) {
            $this->notFountPropOrParam($param);
        }
        return $this->createProxy($info, $param->hasType());
    }

    private function notFountPropOrParam(ReflectionParameter|ReflectionProperty $ref)
    {
        if ($ref instanceof ReflectionParameter) {
            $msg = sprintf('参数%s::%s(%s)的依赖未找到，尝试为 Autowired 注解添加名字限定或者为参数添加类型限定', $ref->getDeclaringClass()->name, $ref->getDeclaringFunction()->name, $ref->name);
        } else {
            $msg = sprintf('属性%s::%s的依赖未找到, 尝试为Autowired注解添加名字限定或者为属性添加类型限定', $ref->getDeclaringClass()->name, $ref->name);
        }
        throw new NotFoundException(message: $msg);
    }

    private function handleAutowiredAttr(Autowired $autowired, ReflectionParameter|ReflectionProperty $param, ?BeanInfo $parent = null, bool $required = true)
    {
        $by = $autowired->by;
        if ($by === Autowired::AUTO) {
            $name = $autowired->value ?: $param->name;
            $info = $this->searchByName($name);
            if ($info === null) {
                if ($autowired->value) {
                    $type = $autowired->value;
                } else {
                    $type = $param->getType();
                    if ($type === null) {
                        throw new RuntimeException('Autowired by type needs to specify the type');
                    } elseif ($type instanceof ReflectionUnionType) {
                        throw new RuntimeException('Autowired cannot accept union types');
                    }
                    $type = $type->getName();
                }
            }
            $info = $this->searchHighestByType($type);
        } elseif ($by === Autowired::BY_NAME) {
            $name = $autowired->value ?: $param->name;
            $info = $this->searchByName($name);
        } else {
            if ($autowired->value) {
                $type = $autowired->value;
            } else {
                $type = $param->getType();
                if ($type === null) {
                    throw new RuntimeException('Autowired by type needs to specify the type');
                } elseif ($type instanceof ReflectionUnionType) {
                    throw new RuntimeException('Autowired cannot accept union types');
                }
                $type = $type->getName();
            }
            $info = $this->searchHighestByType($type);
        }

        if ($info !== null) {
            $result = $this->resolveInstance($info, $parent);
        } else {
            $type = $this->getRefType($param->getType());
            if ($type === null) {
                $this->notFountPropOrParam($param);
            }
            $info = $this->searchHighestByType($type);
            if ($info === null) {
                throw new NotFoundException($type);
            }
            $result = $this->resolveInstance($info, $parent);
        }

        if ($result === null && $required) {
            throw new NotFoundException($info->getName());
        }

        return $result;
    }

    protected function createProxy(BeanInfo $info, bool $haveType)
    {
        if ($haveType) {
            $proxyClass = $info->getDef()->getProxyClass();
            $object = new $proxyClass;
        } else {
            $object = new class {
                use LazyObject;
            };
        }

        return $object->__SET_BEAN_INFO__($info->getName(), $this);
    }

    public function execute($cb, array $extra = [])
    {
        if ($cb instanceof Closure) {
            return $this->executeClosure($cb, $extra);
        } elseif (is_array($cb) && count($cb) === 2) {
            return $this->executeAction($cb, $extra);
        }
        throw new RuntimeException(sprintf('%s is not callback', $cb));
    }

    protected function executeClosure(Closure $cb, array $extra)
    {
        $ref = new ReflectionFunction($cb);
        $params = $ref->getParameters();
        $args = $this->getMethodArgs(null, $params, $extra);
        return $cb(...$args);
    }

    protected function executeAction(array $cb, array $extra)
    {
        $refMethod = new ReflectionMethod($cb[0], $cb[1]);
        $params = $refMethod->getParameters();
        $args = $this->getMethodArgs(null, $params, $extra);

        if (is_string($cb[0])) {
            $obj = $this->get($cb[0]);
        } else {
            $obj = $cb[0];
        }

        return $obj->{$cb[1]}(...$args);
    }

    private function getRefType(ReflectionType $type): ?ReflectionNamedType
    {
        if ($type instanceof ReflectionUnionType) {
            throw new RuntimeException('Bean以及需要注入 Bean 的地方都不能是联合类型');
        }
        return $type;
    }

    private function checkCycleDependency(string $name)
    {
        if (in_array($name, self::$dependencyNames)) {
            throw new CycleDependencyException(sprintf('Cycle dependency: %s, consider use #[Lazy]', $name), self::$dependencyNames);
        }
        self::$dependencyNames[] = $name;
    }

    protected function inject($instance, BeanInfo $info)
    {
        $this->injectSetters($info, $instance);
        $this->injectProps($info, $instance);
    }

    protected function injectSetters(BeanInfo $info, $instance)
    {
        $setters = $info->getDef()->getSetters();
        foreach ($setters as $setter) {
            $this->executeAction([$instance, $setter->getName()], []);
        }
    }

    protected function injectProps(BeanInfo $info, $instance)
    {
        $props = $info->getDef()->getRefProp();
        foreach ($props as $prop) {
            /**@var ReflectionProperty $prop */
            $prop->setAccessible(true);
            $required = AttributeParser::getAttribute($prop->getAttributes(), Required::class)?->newInstance()?->required ?: true;
            $value = $info->getDef()->getPropAttrs($prop->name, Value::class, true);
            if (count($value) > 0) {
                $configValue = $this->handleValueAttr($value[0]->newInstance(), $required);
                if ($required && $prop->hasType()) {
                    $propType = $this->getRefType($prop->getType());
                    $configValue = convert_extra_type($propType, $configValue);
                }
                $prop->setValue($instance, $configValue);
            } elseif ($info->getDef()->propHasAttribute($prop->name, Lazy::class, true)) {
                $lazyInstance = $this->handleLazyAttr($prop); // todo same with proxy???
                $prop->setValue($instance, $lazyInstance);
            } elseif ($autowired = $info->getDef()->getPropAttrs($prop->name, Autowired::class)) {
                $autowiredInstance = $autowired[0]->newInstance();
                $name = $autowiredInstance->value;
                $mode = $autowiredInstance->by;
                $injected = $this->autowired($name, $mode, $prop, $info);
                $prop->setValue($instance, $injected);
            }
        }
    }

    public function start()
    {
        $singleton = array_filter($this->beans, fn (BeanInfo $info) => $info->isSingleton() && !$info->isLazy());
        foreach ($singleton as $item) {
            $this->resolveInstance($item);
        }
    }

    public function clearRequest()
    {
        foreach ($this->beans as $info) {
            if ($info->getScope() === Scope::SCOPE_REQUEST) {
                $info->clearRequest();
            }
        }
    }

    public function clearSession()
    {
        foreach ($this->beans as $info) {
            if ($info->getScope() === Scope::SCOPE_SESSION) {
                $info->clearSession();
            }
        }
    }
}