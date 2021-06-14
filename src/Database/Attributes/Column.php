<?php


namespace Xycc\Winter\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public string $name = '',
        public string $type = '',
        public int $scale = 9,
        public int $limit = 2,
        public bool $notNull = true,
        public string $comment = '',
    )
    {
    }
}