<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\boots;


use stdClass;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Configuration;

#[Configuration]
class TestConfiguration
{
    #[Bean]
    public function id(): stdClass
    {
        $obj = new stdClass();
        $obj->a = TestBean::Xxx;
        return $obj;
    }
}