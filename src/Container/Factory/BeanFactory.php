<?php


namespace Xycc\Winter\Container\Factory;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Swoole\Coroutine;
use Xycc\Winter\Config\Attributes\Value;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\Exceptions\DuplicatedIdentityException;
use Xycc\Winter\Container\Exceptions\InvalidBindingException;
use Xycc\Winter\Container\Exceptions\NotFoundException;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Contract\Attributes\Primary;
use Xycc\Winter\Contract\Attributes\Scope;

#[Bean]
class BeanFactory
{
    /**
     * @var BeanInfo[]
     */
    protected array $beans = [];
    protected BeanDefinitionCollection $manager;

    public function getManyByType(string $type)
    {

    }

    public function addBean(AbstractBeanDefinition $def, ?ReflectionMethod $method)
    {
        if (null === $method) {
            $this->addMethodBean($method, $def);
            return;
        }

        $this->addClassBean($def);
    }

    private function addMethodBean(ReflectionMethod $method, AbstractBeanDefinition $conf)
    {
        $bean = $this->getFirstMethodAttr($conf, $method->name, Bean::class, true);
        if ($bean === null) {
            throw new InvalidBindingException(sprintf('Bean %s must have #[Bean] attribute', $method->getReturnType()));
        }

        $type = $this->parseRefType($method->getReturnType());
        $name = $this->createBeanName($bean, $type?->getName());

        if (isset($this->beans[$name])) {
            throw new DuplicatedIdentityException($conf->getClassName(), $bean->value);
        }

        $info = $this->collectMethodBeanBaseInfo($conf, $method);

        $def = $this->manager->findDefinitionByClass($type?->getname());
        $this->beans[$name] = new BeanInfo($name, ...$info, def: $def, fromConf: true, confName: '', confMethod: $method->name);
    }

    private function getFirstMethodAttr(AbstractBeanDefinition $def, string $method, string $attribute, bool $extends = false)
    {
        $attr = $def->getMethodAttributes($method, $attribute, $extends);
        return (current($attr) ?: null)?->newInstance();
    }

    private function parseRefType(?ReflectionType $type): ?ReflectionNamedType
    {
        if ($type instanceof ReflectionUnionType) {
            throw new InvalidBindingException('Union types must not appear in the container');
        }
        return $type;
    }

    // 查询BeanDefinition集合中的数据， 如果有这个类，并且有Bean注解，就返回这个类
    // 否则查找所有拥有Bean注解的子类， 然后获取到优先级最高的

    protected function createBeanName(Bean $bean, ?string $type): string
    {
        if ($bean->value) {
            return $bean->value;
        }
        return $type ?? throw new InvalidBindingException(sprintf('Bean must have a type or a unique name, class: %s', $type));
    }

    protected function collectMethodBeanBaseInfo(AbstractBeanDefinition $def, ReflectionMethod $method): array
    {
        $methodName = $method->name;
        $order = $this->getFirstMethodAttr($def, $methodName, Order::class)?->value ?: Order::DEFAULT;
        $lazy = $def->methodHasAttribute($methodName, Lazy::class);
        $primary = $def->methodHasAttribute($methodName, Primary::class);
        $scopeAttr = $this->getFirstMethodAttr($def, $methodName, Scope::class);
        $scope = $scopeAttr?->scope ?: Scope::SCOPE_SINGLETON;
        $scopeMode = $scopeAttr?->mode ?: Scope::MODE_DEFAULT;

        return compact('order', 'lazy', 'primary', 'scope', 'scopeMode');
    }

    protected function addClassBean(AbstractBeanDefinition $def)
    {
        $bean = $this->getFirstClassAttr($def, Bean::class, true);
        if ($bean === null) {
            throw new InvalidBindingException(sprintf('Bean %s must have #[Bean] attribute', $def->getClassName()));
        }

        $name = $this->createBeanName($bean, $def->getClassName());

        if (isset($this->beans[$name])) {
            throw new DuplicatedIdentityException($def->getClassName(), $bean->value);
        }

        $info = $this->collectClassBeanBaseInfo($def);

        $this->beans[$name] = new BeanInfo($name, ...$info, def: $def, fromConf: false);
    }

    private function getFirstClassAttr(AbstractBeanDefinition $def, string $attribute, bool $extends = false)
    {
        $attr = $def->getClassAttributes($attribute, $extends);
        return (current($attr) ?: null)?->newInstance();
    }

    protected function collectClassBeanBaseInfo(AbstractBeanDefinition $def): array
    {
        $order = $this->getFirstClassAttr($def, Order::class)?->value ?: Order::DEFAULT;
        $lazy = $def->classHasAttribute(Lazy::class);
        $primary = $def->classHasAttribute(Primary::class);
        $scopeAttr = $this->getFirstClassAttr($def, Scope::class);
        $scope = $scopeAttr?->scope ?: Scope::SCOPE_SINGLETON;
        $scopeMode = $scopeAttr?->mode ?: Scope::MODE_DEFAULT;

        return compact('order', 'lazy', 'primary', 'scope', 'scopeMode');
    }

    public function getInstance(array $info, array $extra = [], AbstractBeanDefinition $parent = null, bool $hasType = true): mixed
    {
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

    protected function resolveFromConf(array $info)
    {
        $conf = $this->get($info['def']->getClassName());
        if ($conf === null) {
            throw new NotFoundException($info['def']->getClassName());
        }
    }

    public function get(string $name)
    {
        if (!isset($this->beans[$name])) {
            throw new NotFoundException($name);
        }

        $info = $this->beans[$name];

        return $this->getByName($info) ?? $this->getByType();
    }

    public function getByName(string $name)
    {

    }

    public function getByType(string $class)
    {
        $info = $this->getBeanClass($class);
        if ($info !== null) {
            return $this->resolveInstance($info);
        }
    }

    /**
     * 获取符合指定的类的信息
     */
    private function getBeanClass(string $class): ?BeanInfo
    {
        $classes = array_filter($this->beans, fn (array $info) => $info['def']?->getClassName() === $class && $info['def']?->classHasAttribute(Bean::class, true));
        if (count($classes) === 1) {
            return current($classes);
        } elseif (count($classes) === 0) {
            return null;
        } else {
            throw new RuntimeException('unknown error: ' . json_encode($classes));
        }
    }

    protected function resolveInstance(BeanInfo $info)
    {
        switch ($info->scope) {
            case Scope::SCOPE_SINGLETON:
                if ($info->instance !== null) {
                    $instance = $info->instance;
                }
                $instance = $this->resolveInstance1($info);
                break;
        }
        // inject
    }

    protected function resolveInstance1(BeanInfo $info)
    {
        $refClass = $info->def->getRefClass();
        $constructor = $refClass->getConstructor();
        if ($constructor === null) {
            return $refClass->newInstanceWithoutConstructor();
        }

        $params = $constructor->getParameters();
        if (count($params) === 0) {
            return $refClass->newInstance();
        }

        $args = $this->getMethodArgs($params, $info->def, $extra);
    }

    /**
     * @param ReflectionParameter[] $params
     */
    protected function getMethodArgs(array $params, AbstractBeanDefinition $def, array $extra = [])
    {
        $args = [];
        foreach ($params as $param) {
            $arg = $this->handlePredefinedAttributes($def, $param);
        }
    }

    /**
     * 几个预定义的注解
     * #Value 获取配置的值
     * #Lazy 返回一个延迟解析的代理
     * #Autowired 返回指定名字的依赖，如果没有指定名字， 那就找类型， 如果符合的类型有多个， 找和参数名字相同的， 没有相同的就找优先级最高的
     */
    protected function handlePredefinedAttributes(AbstractBeanDefinition $def, ReflectionParameter $param)
    {
        $methodName = $param->getDeclaringFunction()->name;
        if ($configAttr = $def->getParamAttrs($methodName, $param->name, Value::class)) {
            $config = $this->get('config');
            $path = $configAttr[0]->newInstance()->path;
            return $config->get($path);
        } elseif (count($def->getParamAttrs($methodName, $param->name, Lazy::class))) {

        }

        if ($configAttr = $param->getAttributes(Value::class)) {
            $config = $this->get('config');
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
}