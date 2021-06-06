<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Route;


use Closure;
use PHPUnit\Framework\TestCase;
use Xycc\Winter\Route\Attributes\Route;
use Xycc\Winter\Route\Exceptions\DuplicatedRouteException;
use Xycc\Winter\Route\Exceptions\InvalidRouteException;
use Xycc\Winter\Route\Exceptions\RouteMatchException;
use Xycc\Winter\Route\Node;
use Xycc\Winter\Route\RouteItem;
use Xycc\Winter\Route\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRegisterRoute()
    {
        $this->router->addRoute(Route::GET, '/a/b', 'default', self::class, 'testRegisterRoute');
        $this->expectException(RouteMatchException::class);
        $this->router->match('/a/b/c', Route::GET);

        $this->router->match('/a/b', Route::POST);

        $this->router->addRoute(Route::ANY, '/a/b/c', 'default', self::class, 'testRegisterRoute');
    }

    public function testCatchRouteNode()
    {
        $this->expectException(InvalidRouteException::class);
        $this->router->addRoute(Route::ANY, '/a/*b/c', 'default', handler: fn () => '');
    }

    public function testDuplicatedRoute()
    {
        $this->router->addRoute(Route::ANY, '/a/b/c', 'default', handler: fn () => '');
        $this->expectException(DuplicatedRouteException::class);
        $this->router->addRoute(Route::ANY, '/a/b/c', 'xxx', handler: fn () => '');
    }

    public function testMatch()
    {
        $this->router->addRoute(Route::GET, '/a/*b', 'default', self::class, 'testRegisterRoute');
        $this->router->match('/a/cc/dd', Route::GET);
        $route = $this->router->match('/a/cc/dd', Route::GET);
        $this->assertInstanceOf(RouteItem::class, $route);
        $this->assertEquals(['b' => 'cc/dd'], $route->getNamedParams());

        $this->router->addRoute(Route::GET, '/aa/bb', 'default', self::class, 'testRegisterRoute');
        $route = $this->router->match('/aa/bb', Route::GET);
        $this->assertEmpty($route->getParams());

        $this->router->addRoute(Route::GET, '/b/*', 'default', handler: fn () => 1);
        $catch = $this->router->match('/b/bb/dd', Route::GET);
        $this->assertEquals(['catch' => 'bb/dd'], $catch->getNamedParams());
    }

    public function testMatchParam()
    {
        $this->router->addRoute(Route::GET, '/a/{b}/c', 'default', self::class, 'testRegisterRoute');
        $route = $this->router->match('/a/bb/c', Route::GET);
        $this->assertEquals(['b' => 'bb'], $route->getNamedParams());
    }

    public function testMatchRegex()
    {
        $this->router->addRoute(Route::GET, '/a/{b:\d+}/c', 'default', self::class, 'testRegisterRoute');
        $route = $this->router->match('/a/123/c', Route::GET);
        $this->assertEquals(['b' => '123'], $route->getNamedParams());
    }

    public function testMultiPattern()
    {
        $this->router->addRoute(Route::GET, '/a/{b:\\d{2,3}[a-zA-Z]+}/c', 'default', self::class, 'testRegisterRoute');
        $this->router->addRoute(Route::GET, '/a/{b:\\d{2,3}[a-zA-Z]+}', 'default', self::class, 'testRegisterRoute');
        $this->router->addRoute(Route::GET, '/a/{c}/c', 'default', self::class, 'testRegisterRoute');
        $this->router->addRoute(Route::GET, '/a/b/c', 'default', self::class, 'testRegisterRoute');
        $this->router->addRoute(Route::GET, '/a/*b', 'default', self::class, 'testRegisterRoute');

        $re = $this->router->match('/a/22bb/c', Route::GET);
        $this->assertEquals('\\d{2,3}[a-zA-Z]+', $re->getNode()->getParent()->getRegex());
        $this->assertEquals('{b:\\d{2,3}[a-zA-Z]+}', $re->getNode()->getParent()->getPath());

        $this->expectException(InvalidRouteException::class);
        $this->router->addRoute(Route::GET, '/c/{123a:\\d{2,3}}', 'default', handler: fn () => 1);

        $this->router->addRoute(Route::GET, '/d/{e:\\d+{2,3}+{2,3}\w', 'default', handler: fn () => 1);
        $this->router->match('/d/e', Route::GET);

        $route1 = $this->router->match('/a/b/c', Route::GET);
        $this->assertEquals([], $route1->getParams());
        $this->assertTrue($route1->getNode()->getMode() === Node::Static);

        $route2 = $this->router->match('/a/22dd/c', Route::GET);
        $this->assertEquals(['b' => '22dd'], $route2->getNamedParams());
        $this->assertTrue($route2->getNode()->getMode() === Node::Static);
        $this->assertTrue($route2->getNode()->getParent()->getMode() === Node::Regex);

        $route3 = $this->router->match('/a/22222dd/c', Route::GET);
        $this->assertEquals(['c' => '22222dd'], $route3->getNamedParams());
        $this->assertTrue($route3->getNode()->getMode() === Node::Static);
        $this->assertTrue($route3->getNode()->getParent()->getMode() === Node::Param);

        $route4 = $this->router->match('/a/ddd/eee/fff', Route::GET);
        $this->assertEquals(['b' => 'ddd/eee/fff'], $route4->getNamedParams());
        $this->assertTrue($route4->getNode()->getMode() === Node::Catch);
        $this->assertTrue($route4->getNode()->getClass() === self::class);
        $this->assertTrue($route4->getNode()->getMethod() === 'testRegisterRoute');
    }

    public function testHandler()
    {
        $this->router->addRoute(Route::ANY, '/a/b', 'default', handler: fn () => 1);
        $route2 = $this->router->match('/a/b', Route::GET);
        $this->assertInstanceOf(Closure::class, $route2->getNode()->getHandler());
        $this->assertEquals(1, $route2->getNode()->getHandler()());
    }

    public function testSpecialMethods()
    {
        $this->router->addRoute('OTHER', '/a/b/c', 'default', self::class, 'testRegisterRoute');
        $route = $this->router->match('/a/b/c', 'OTHER');
        $this->assertEquals('other', $route->getMethod());
    }
}