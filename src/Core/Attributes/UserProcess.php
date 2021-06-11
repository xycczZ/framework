<?php


namespace Xycc\Winter\Core\Attributes;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Xycc\Winter\Contract\Attributes\Component;

#[Attribute(Attribute::TARGET_CLASS)]
class UserProcess extends Component
{
    public const PIPE_NONE = 0;
    public const PIPE_STREAM = 1;
    public const PIPE_DRAM = 2;

    public function __construct(
        public ?string $value = null,
        public bool $redirectStdinStdout = false,
        #[ExpectedValues(flagsFromClass: self::class)]
        public int $pipeType = self::PIPE_NONE,
        public bool $enableCoroutine = true)
    {
    }
}