<?php


namespace Xycc\Winter\Container\Factory;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Xycc\Winter\Config\Attributes\Value;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\Exceptions\NotFoundException;
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
    protected BeanDefinitionCollection $manager;

    public function getManyByType(string $type)
    {

    }

    public function get(string $name, ?string $type = null)
    {
        if ($name && $type) {
            $info = $this->searchByName($name);
        } else {
            $info = $this->searchByName($name) ?? $this->searchByType($name);
        }
    }

    public function getByName(string $name)
    {
        $info = $this->beans[$name];
        $this->resolveInstance($info);
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
        switch ($info->getScope()) {
            case Scope::SCOPE_SINGLETON:
                $instance = $info->getInstance();
                if ($instance === null) {
                    $instance = $this->createInstance($info);
                }
                break;
        }
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

        // inject
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

        $args = $this->getMethodArgs($info, $params);
        return $class->newInstanceArgs($args);
    }

    /**
     * @param ReflectionParameter[] $params
     */
    private function getMethodArgs(BeanInfo $info, array $params)
    {
        $args = [];
        foreach ($params as $param) {
            $arg = $this->handlePredefinedAttributes($info, $param);
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
                $infos = $this->factory->findInfosByType($type);
                $info = $this->chooseHighestPriority($infos);
                $arg = $info->getInstance();
                if ($arg === null) {
                    $required = $this->def->getParamAttrs($param->getDeclaringFunction()->name, $param->name, Required::class, true);
                    if (count($required) === 0 || $required[0]->newInstance()->required === true) {
                        throw new NotFoundException(sprintf('%s::%s(%s) 未找到依赖，尝试使用Autowired注解给予名字', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
                    }
                }
                $args[] = $arg;
                continue;
            }

            throw new NotFoundException(sprintf('%s::%s(%s) 未找到依赖，尝试使用Autowired注解给予名字', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
        }

        return $args;
    }

    /**
     * 处理几个预定义的注解
     * 1.
     */
    private function handlePredefinedAttributes(BeanInfo $info, ReflectionParameter $param)
    {
        $methodName = $param->getDeclaringFunction()->name;
        if ($configAttr = $info->getDef()->getParamAttrs($methodName, $param->name, Value::class)) {
            $config = $this->getByName('config');
            $path = $configAttr[0]->newInstance()->path;
            if ($config->has($path)) {
                return $config->get($path);
            }
            $required = $info->getDef()->getParamAttrs($methodName, $param->name, Required::class);
            if ($required) {
                throw new RuntimeException(sprintf('required config `%s` not found', $path));
            }
            return null;
        } elseif (count($info->getDef()->getParamAttrs($methodName, $param->name, Lazy::class))) {
            // lazy 如果有autowired注解， 按照autowired注解获取BeanInfo， 否则按照类型获取
            $autowiredAttr = $info->getDef()->getParamAttrs($methodName, $param->name, Autowired::class);
            $type = $param->getType();
            $type = $this->getRefType($type);
            if (count($autowiredAttr) === 0) {
                // 如果没有 autowired 注解， 根据类型注入
                if ($type === null) {
                    throw new NotFoundException('Lazy 注解的参数必须有类型或者同时有 Autowired 注解');
                }
                $name = $type->getName();
            } else {
                $beanName = $autowiredAttr[0]->newInstance()->value;
                $name = $beanName ?: $type->getName();
                $name = $name ?: $param->name;
            }
            $info = $this->factory->findInfoByName($name);
            if ($info === null) {
                throw new NotFoundException(sprintf('%s::%s(%s) 未找到可以注入的依赖, 尝试添加类型提示或者 Autowired 注解加入名字限定', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
            }
            return $info->getProxyInstance($param->hasType());
        } elseif ($autowiredAttr = $this->def->getParamAttrs($methodName, $param->name, Autowired::class)) {
            $name = $autowiredAttr[0]->newInstance()->value ?: $param->name;
            $info = $this->factory->findInfoByName($name);
            if ($info !== null) {
                return $info->getInstance();
            }
            $type = $this->getRefType($param->getType());
            if ($type === null) {
                throw new NotFoundException(sprintf('%s::%s(%s)的依赖未找到，尝试为 Autowired 注解添加名字限定或者为参数添加类型限定', $param->getDeclaringClass()->name, $param->getDeclaringFunction()->name, $param->name));
            }
            $infos = $this->factory->findInfosByType($type);
            $info = $this->chooseHighestPriority($infos);
            return $info->getInstance();
        }
        return null;
    }

    private function chooseHighestPriority(array $infos): BeanInfo
    {
        if (count($infos) === 1) {
            return current($infos);
        } elseif (count($infos) === 0) {
            throw new NotFoundException();
        }

        $primary = array_filter($infos, fn (BeanInfo $info) => $info->primary);
        if (count($primary) > 1) {
            throw new MultiPrimaryException('多个 Primary 注解: ' . implode(',', array_map(fn (BeanInfo $info) => $info->name, $primary)));
        } elseif (count($primary) === 1) {
            return current($primary);
        }

        usort($infos, fn (BeanInfo $a, BeanInfo $b) => $a->order <=> $b->order);
        $infos = array_values($infos);
        if ($infos[0]->order === $infos[1]->order) {
            throw new PriorityDecidedException('优先级冲突: ' . implode(',', array_map(fn (BeanInfo $info) => $info->name, array_filter($infos, fn ($info) => $info->order === $infos[0]->order))));
        }
        return $infos[0];
    }

    private function getRefType(ReflectionType $type): ?ReflectionNamedType
    {
        if ($type instanceof ReflectionUnionType) {
            throw new RuntimeException('Bean以及需要注入 Bean 的地方都不能是联合类型');
        }
        return $type;
    }
}