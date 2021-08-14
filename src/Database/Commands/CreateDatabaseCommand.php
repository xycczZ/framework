<?php

namespace Xycc\Winter\Database\Commands;

use Doctrine\DBAL\Driver\PDO\MySQL\Driver as MysqlDriver;
use Doctrine\DBAL\Driver\PDO\OCI\Driver as OCIDriver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SqliteDriver;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver as SqlSrvDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xycc\Winter\Command\Attributes\AsCommand;
use Xycc\Winter\Config\Attributes\Value;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Config\ConfigContract;

#[AsCommand('db:create', 'create database if not exists')]
class CreateDatabaseCommand extends Command
{
    #[Value('db.driver')]
    private string $driver;

    #[Autowired]
    private ConfigContract $config;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $driver = $this->getDriver();

        $driverConfig = $this->getDriverConfig();
        if (!isset($driverConfig['dbname'])) {
            $text = (new OutputFormatter())->getStyle('error')->apply('dbname is not set');
            $output->writeln($text);
            return self::INVALID;
        }

        $dbName = $driverConfig['dbname'];
        unset($driverConfig['dbname']);

        $conn = DriverManager::getConnection($driverConfig + ['driverClass' => $driver]);
        try {
            $conn->createSchemaManager()->createDatabase($dbName);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'database exists')) {
                $output->writeln('database ' . $dbName . ' already exists');
                return self::FAILURE;
            }
            throw $e;
        }

        $output->writeln('Created database ' . $dbName);
        return self::SUCCESS;
    }

    protected function getDriver(): string
    {
        return match ($this->driver) {
            'mysql' => MysqlDriver::class,
            'postgres' => PgDriver::class,
            'sqlsrv' => SqlSrvDriver::class,
            'sqlite' => SqliteDriver::class,
            'oci' => OCIDriver::class,
            default => throw new RuntimeException('unknown driver'),
        };
    }

    protected function getDriverConfig(): array
    {
        return $this->config->get('db.' . $this->driver);
    }
}