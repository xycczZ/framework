<?php


namespace Xycc\Winter\Database\Pools;

use Doctrine\DBAL\Connection as DoctrineConn;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * @mixin QueryBuilder
 */
class Connection extends DoctrineConn
{
    private ConnectionPool $pool;

    public function setPool(ConnectionPool $pool)
    {
        $this->pool = $pool;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->createQueryBuilder()->{$name}(...$arguments);
    }

    public function __destruct()
    {
        $this->pool->release($this);
    }
}