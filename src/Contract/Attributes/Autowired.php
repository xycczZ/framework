<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Attributes;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Autowired
{
    public const AUTO = 0;
    public const BY_TYPE = 1;
    public const BY_NAME = 2;

    public function __construct(
        public ?string $value = null,
        #[ExpectedValues(flagsFromClass: self::class)]
        public int $autowiredMode = self::AUTO,
        public bool $required = true)
    {
    }
}