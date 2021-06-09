<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\Factory;


use Xycc\Winter\Container\Exceptions\MultiPrimaryException;
use Xycc\Winter\Container\Exceptions\PriorityDecidedException;

trait SearchBeanInfo
{
    protected function searchByName(string $name): ?BeanInfo
    {
        return $this->beans[$name] ?? null;
    }

    protected function searchByType(string $type): ?BeanInfo
    {
        $beans = array_values(array_filter($this->beans, fn (BeanInfo $info) => $info->getDef()->getClassName() === $type || is_subclass_of($info->getDef()->getClassName(), $type)));
        if (count($beans) === 0) {
            return null;
        } elseif (count($beans) === 1) {
            return $beans[0];
        }

        $primary = array_filter($beans, fn (BeanInfo $info) => $info->isPrimary());
        if (count($primary) === 1) {
            return current($primary);
        } elseif (count($primary) > 1) {
            throw new MultiPrimaryException(sprintf('Too many #[Primary]: %s', implode(',', array_map(fn (BeanInfo $info) => $info->getName(), $primary))));
        }

        throw new PriorityDecidedException(sprintf('%s types must have one #[Primary]', implode(',', array_map(fn (BeanInfo $info) => $info->getDef()->getClassName(), $beans))));
    }
}