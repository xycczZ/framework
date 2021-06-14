<?php


namespace Xycc\Winter\Database\Query;


use Swoole\Coroutine;
use Xycc\Winter\Database\Model;
use Xycc\Winter\Database\Pools\Connection;
use Xycc\Winter\Database\Pools\ConnectionPool;

/**
 * @mixin Connection
 */
class QueryBuilder
{
    private Model $model;
    private ConnectionPool $pool;
    private ?Connection $connection = null;

    protected array $end = [
        'executeQuery',
        'executeStatement',
        'fetch*',
        'getSQL'
    ];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->pool = Coroutine::getContext()['app']->get(ConnectionPool::class);
    }

    public function __call(string $method, array $args)
    {
        if (! $this->connection) {
            $this->connection = $this->pool->get();
        }

        $result = $this->connection->{$method}(...$args);
        if ($this->isEnd($method)) {
            $this->pool->release($this->connection);
        }
        return $result;
    }

    protected function isEnd(string $method): bool
    {
        foreach ($this->end as $item) {
            if (str_contains($item, '*')) {
                $regex = str_replace('*', '.*?', $item);
                if (preg_match('#'.$regex.'#', $method)) {
                    return true;
                }
            } else {
                if ($item === $method) {
                    return true;
                }
            }
        }

        return false;
    }

    public function __destruct()
    {
        if ($this->connection) {
            $this->pool->release($this->connection);
        }
    }
}