<?php


namespace Xycc\Winter\Database\Pools;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as MysqlDriver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SqliteDriver;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver as SqlSrvDriver;
use Doctrine\DBAL\Driver\PDO\OCI\Driver as OCIDriver;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Config\ConfigContract;

#[Component]
#[NoProxy]
class ConnectionPool
{
    private ?\Swoole\ConnectionPool $pool = null;

    public function __construct(
        private ConfigContract $config,
    )
    {
        $this->setup();
    }

    public function setup()
    {
        $driver = $this->config->get('db.driver');
        $size = $this->config->get('db.pool-size.' . $driver, \Swoole\ConnectionPool::DEFAULT_SIZE);
        $this->pool = new \Swoole\ConnectionPool(fn () => $this->createConnection($driver), $size);
    }

    protected function createConnection(string $driver): Connection
    {
        $config = $this->config->get('db.'.$driver);
        $dbDriver =  match ($driver) {
            'mysql' => new MysqlDriver(),
            'postgres' => new PgDriver(),
            'sqlsrv' => new SqlSrvDriver(),
            'sqlite' => new SqliteDriver(),
            'oci' => new OCIDriver()
        };

        $conn =  new \Xycc\Winter\Database\Pools\Connection($config, $dbDriver);
        $conn->setPool($this);
        $conn->connect();
        return $conn;
    }

    public function release(Connection $connection)
    {
        $this->pool->put($connection);
    }

    public function get(): Connection
    {
        return $this->pool->get();
    }
}