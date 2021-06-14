<?php


namespace Xycc\Winter\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey extends TableKey
{
    public function __construct(
        public string $indexName = '',
        public string|array $fields = '',
        public bool $autoIncr = true,
        public string $type = 'int',
    )
    {
    }
}