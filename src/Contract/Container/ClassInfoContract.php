<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Container;


use ReflectionAttribute;

interface ClassInfoContract
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAllClassAttributes(): array;

    /**
     * get all specified attributes of current class
     *
     * @param string $attribute Attribute class FQN, used to filter attributes
     * @param bool   $extends   determines whether need to search for subclasses
     * @return ReflectionAttribute[]
     */
    public function getClassAttributes(string $attribute, bool $extends = false): array;

    /**
     * this class contains the specified attribute?
     *
     * @param string $attribute
     * @param bool   $extends
     * @return bool
     */
    public function classHasAttribute(string $attribute, bool $extends = false): bool;
}