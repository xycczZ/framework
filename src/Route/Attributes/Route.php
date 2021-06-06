<?php
declare(strict_types=1);

namespace Xycc\Winter\Route\Attributes;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public const GET = 'get';
    public const POST = 'post';
    public const PUT = 'put';
    public const PATCH = 'patch';
    public const DELETE = 'delete';
    public const OPTIONS = 'options';

    public const ANY = '';

    public function __construct(
        #[ExpectedValues(flagsFromClass: self::class)] public string $method = self::ANY,
        public string $path = '',
        public string $group = 'default',
    )
    {
    }
}