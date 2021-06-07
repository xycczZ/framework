<?php


namespace Xycc\Winter\Core\Attributes;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Xycc\Winter\Contract\Attributes\Bean;

#[Attribute(Attribute::TARGET_CLASS)]
class UserProcess extends Bean
{
    public const PIPE_NONE = 0;
    public const PIPE_STREAM = 1;
    public const PIPE_DRAM = 2;

    public function __construct(
        public bool $redirectStdinStdout = false,
        #[ExpectedValues(flagsFromClass: self::class)]
        public int $pipeType = self::PIPE_NONE,
        public bool $enableCoroutine = true)
    {
        parent::__construct();
    }
}