<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container\boots;


use Xycc\Winter\Config\Attributes\Value;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Primary;

#[Bean('testBean')]
#[Primary]
class TestBean implements TestBeanInterface
{
    public const Xxx = 'xxx';

    #[Autowired]
    private TestBean2 $bean2;

    private TestBean2 $same;

    private TestBean2 $same2;

    #[Value('app.name')]
    public string $winterConfig;

    public function callBean2Method()
    {
        return $this->bean2->returnConst();
    }

    public function getSame()
    {
        return $this->same;
    }

    public function getSame2()
    {
        return $this->same2;
    }

    #[Autowired]
    public function setSame(TestBean2 $same)
    {
        $this->same = $same;
    }

    #[Autowired]
    public function setSame2()
    {
        $this->same2 = new TestBean2();
    }

    public function returnConst(): string
    {
        return self::Xxx;
    }
}