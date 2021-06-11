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

    /**
     * @return BeanInfo[]
     */
    protected function searchByType(string $type, bool $isBean = true): array
    {
        return array_values(array_filter($this->beans, fn (BeanInfo $info) => $info->getDef()?->getClassName() === $type || is_subclass_of($info->getDef()?->getClassName(), $type) && (!$isBean || $info->getDef()->isBean())));
    }

    protected function searchHighestByType(string $type, bool $isBean = true): ?BeanInfo
    {
        $beans = $this->searchByType($type, $isBean);

        $beans = array_filter($beans, fn (BeanInfo $info) => $info->getDef()->getRefClass()?->isInstantiable());

        if (count($beans) === 0) {
            return null;
        } elseif (count($beans) === 1) {
            return current($beans);
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