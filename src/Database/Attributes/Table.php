<?php


namespace Xycc\Winter\Database\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\Scope;

#[Attribute(Attribute::TARGET_CLASS)]
#[Scope(Scope::SCOPE_PROTOTYPE)]
class Table extends Component
{
    public function __construct(
        public ?string $value = null,
        public string $tableName = '',
        public string $charset = 'utf8mb4',
        public string $engine = 'InnoDB',
        public string $comment = '',
    )
    {
    }
}