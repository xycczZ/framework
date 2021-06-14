<?php


namespace Xycc\Winter\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TableKey
{
    public function __construct(
        public string $indexName = '', // index name
        public string|array $fields = '', // table fields
    )
    {
    }
}