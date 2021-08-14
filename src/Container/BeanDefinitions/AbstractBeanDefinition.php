<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\BeanDefinitions;


use JetBrains\PhpStorm\Pure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use SplFileInfo;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\Exceptions\InvalidBindingException;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Container\BeanDefinitionContract;

abstract class AbstractBeanDefinition implements BeanDefinitionContract
{
    use ClassInfo, MethodInfo, PropInfo, ParamInfo, ParseMetadata;

    protected BeanDefinitionCollection $manager;

    protected bool $canProxy = false;

    protected ?string $proxyClass = null;

    protected ?SplFileInfo $fileInfo = null;

    protected function createProxy(): string
    {
        return $this->manager->proxyManager->generate($this);
    }

    public function isConfiguration(): bool
    {
        return $this->isConfiguration;
    }

    public function setUpConfiguration(): array
    {
        return [];
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getName(): ?string
    {
        $beans = $this->getClassAttributes(Bean::class, true);
        if (count($beans) > 0) {
            $name = $beans[0]->newInstance()->value ?: $this->className;
        } else {
            $name = $this->className;
        }

        return $name;
    }

    /**
     * @return ReflectionMethod[]
     */
    public function getSetters(): array
    {
        return $this->setters;
    }

    /**
     * @return array<ReflectionMethod>
     */
    public function getConfigurationMethods(): array
    {
        return $this->configurationMethods;
    }

    public function haveConfigurationMethods(): bool
    {
        return count($this->configurationMethods) > 0;
    }

    public function getFile(): ?SplFileInfo
    {
        return $this->fileInfo;
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

    public function getRefParams(
        string $method,
        int|string|null $paramNameOrIndex = null
    ): array|null|ReflectionParameter {
        if ($paramNameOrIndex === null) {
            return $this->refParams[$method] ?? null;
        }

        $name = $this->convertPositionToName($method, $paramNameOrIndex);
        return $this->refParams[$method][$name] ?? null;
    }

    /**
     * @param SplFileInfo $file
     * @param string      $fqn
     *
     * @throws ReflectionException
     */
    public function reload(SplFileInfo $file, string $fqn): void
    {
        $this->fileInfo = $file;
        $this->className = $fqn;
        $this->refClass = new ReflectionClass($fqn);
        $this->parseMetadata($this->refClass);
    }

    /**
     * @return array<ReflectionAttribute>
     */
    protected function filterAttribute(array $attributes, string $attribute, bool $extends = false): array
    {
        return array_values(array_filter(
            $attributes,
            fn(ReflectionAttribute $attr) => $extends
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

    protected function getRefType(?ReflectionType $type): ?ReflectionNamedType
    {
        if ($type instanceof ReflectionUnionType) {
            throw new InvalidBindingException('The types of beans or autowired objects could not be union type');
        }
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $type;
    }

    public function canProxy(): bool
    {
        return $this->canProxy;
    }

    public function getProxyClass(): string
    {
        if ($this->proxyClass) {
            return $this->proxyClass;
        }
        return $this->proxyClass = $this->createProxy();
    }
}