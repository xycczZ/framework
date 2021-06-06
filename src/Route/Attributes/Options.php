<?php
declare(strict_types=1);

namespace Xycc\Winter\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Options extends Route
{
    public function __construct(string $path = '')
    {
        parent::__construct(Route::OPTIONS, $path);
    }
}