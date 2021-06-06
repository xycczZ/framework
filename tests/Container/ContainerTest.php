<?php


namespace Xycc\Winter\Tests\Container;


use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Tests\Container\boots\TestBean;
use Xycc\Winter\Tests\Container\boots\TestBean2;
use Xycc\Winter\Tests\Container\boots\TestBeanInterface;
use Xycc\Winter\Tests\Container\lazy\A;
use Xycc\Winter\Tests\Container\lazy\APrototype;
use Xycc\Winter\Tests\Container\lazy\LazyB;

class ContainerTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->app->appendBoots(TestBootstrap::class);
        //        $boots[] = ContainerBootstrap::class;
        $_ENV['winter_app.env'] = 'test';
        $this->app->start(__DIR__ . '/../..');
    }

    public function testGetInstance()
    {
        $resolved = $this->app->get('id');
        $this->assertInstanceOf(stdClass::class, $resolved, 'get wrong type');
        $this->assertEquals(TestBean::Xxx, $resolved->a, 'get wrong object');
    }

    public function testResolveInstance()
    {
        $tb = $this->app->get(TestBean::class);
        $this->assertInstanceOf(TestBean::class, $tb, 'get wrong type');
        $tb2 = $this->app->get(TestBean::class);
        $this->assertEquals($tb, $tb2, 'different instances were obtained twice');

        $tb3 = $this->app->get('testBean');
        $this->assertEquals($tb, $tb3, 'different instances were obtained twice');
    }

    public function testGetAttribute()
    {
        $test1 = $this->app->get(TestBeanInterface::class);
        $this->assertInstanceOf(TestBeanInterface::class, $test1);
        $this->assertInstanceOf(TestBean::class, $test1);
        $test2 = $this->app->get(TestBean::class);
        $this->assertEquals($test1, $test2);
        $this->assertEquals('xxx', $test1->returnConst());
    }

    public function testLazy()
    {
        $a = $this->app->get(A::class);
        $ref = new ReflectionClass($a);
        $bProp = $ref->getProperty('b');
        $bValue = $bProp->getValue($a);
        $refClass = new ReflectionClass($bValue);

        $this->assertTrue($refClass->getName() !== LazyB::class, '#[Lazy] did not create a sub class declaration');
        $this->assertInstanceOf(LazyB::class, $bValue, '#[Lazy] did not create a sub class object');

        $str = $a->b->returnB();
        $this->assertEquals($str, 'B', 'get wrong result');

        $this->assertTrue(is_subclass_of($a->b, LazyB::class), '#[Lazy] props have not been replaced with the original');
        $a->b->returnB();
        $this->assertTrue(is_subclass_of($a->b, LazyB::class), '#[Lazy] props have not been replaced with the original');
    }

    public function testPrototypeProxy()
    {
        $a = $this->app->get(APrototype::class);
        $ref = new ReflectionClass($a);
        $bProp = $ref->getProperty('b');
        $bValue = $bProp->getValue($a);
        $refClass = new ReflectionClass($bValue);

        $this->assertTrue($refClass->getName() !== LazyB::class, '#[Lazy] did not create a sub class declaration');
        $this->assertInstanceOf(LazyB::class, $bValue, '#[Lazy] did not create a sub class object');

        $str = $a->b->returnB();
        $this->assertEquals($str, 'B', 'get wrong result');

        $this->assertTrue(get_class($a->b) !== LazyB::class, 'Lazy property of prototype bean have wrong type');
    }

    public function testInjectProp()
    {
        $bean1 = $this->app->get(TestBean::class);
        $this->assertEquals(TestBean2::Xxx, $bean1->callBean2Method());
        $this->assertEquals(TestBean2::Xxx, $bean1->getSame()->returnConst());
        $this->assertEquals(TestBean2::Xxx, $bean1->getSame2()->returnConst());

        $this->assertEquals($this->app->get(TestBean2::class), $bean1->getSame());
        $this->assertEquals('Winter', $bean1->winterConfig);
    }
}