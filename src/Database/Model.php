<?php


namespace Xycc\Winter\Database;


use Doctrine\DBAL\Exception as DBALException;
use Xycc\Winter\Contract\Components\AttributeParser;
use Xycc\Winter\Database\Attributes\Table;
use Xycc\Winter\Database\Query\QueryBuilder;

/**
 * @mixin QueryBuilder
 */
abstract class Model
{
    protected bool $isNew;

    private static string $tableName = '';

    protected QueryBuilder $query;

    public function __construct()
    {
        $this->query = new QueryBuilder($this);
    }

    /**
     * @throws DBALException
     */
    public function first($id)
    {
        $result = $this->where('id = ?')
            ->setParameter(0, $id)
            ->select('id', 'name')
            ->from($this->table())
            ->fetchAssociative();
        return $result;
    }

    public function table(): string
    {
        if (! self::$tableName) {
            $attrs           = AttributeParser::parseClass(static::class);
            self::$tableName = $attrs[Table::class]->newInstance()->tableName;
        }
        return self::$tableName;
    }

    public function __call(string $method, array $args)
    {
        return $this->query->{$method}(...$args);
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}