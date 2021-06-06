<?php
declare(strict_types=1);

namespace Xycc\Winter\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends Route
{
    public function __construct(string $path = '')
    {
        parent::__construct(Route::DELETE, $path);
    }
}