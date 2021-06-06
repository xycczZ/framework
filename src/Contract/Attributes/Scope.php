<?php
declare(strict_types=1);

namespace Xycc\Winter\Contract\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Scope
{
    public const SCOPE_SINGLETON = 1;
    public const SCOPE_SESSION = 2;
    public const SCOPE_REQUEST = 3;
    public const SCOPE_PROTOTYPE = 4;
    public const SCOPES = [
        self::SCOPE_SINGLETON,
        self::SCOPE_SESSION,
        self::SCOPE_REQUEST,
        self::SCOPE_PROTOTYPE,
    ];

    public const MODE_DEFAULT = 1;
    public const MODE_PROXY = 2;
    public const MODES = [
        self::MODE_DEFAULT,
        self::MODE_PROXY,
    ];

    public function __construct(
        public int $scope = Scope::SCOPE_SINGLETON,
        public int $mode = Scope::MODE_DEFAULT,
    )
    {
    }
}