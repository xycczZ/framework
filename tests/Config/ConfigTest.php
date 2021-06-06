<?php


namespace Xycc\Winter\Tests\Config;


use PHPUnit\Framework\TestCase;
use Xycc\Winter\Config\Config;

class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
        $_ENV['winter_app.env'] = 'test';
        $this->config->scan(__DIR__);
    }

    public function testGetConfig()
    {
        $str = $this->config->get('conf.string');
        $this->assertEquals('testStringValue', $str, 'get incorrect string');

        $int = $this->config->get('conf.int');
        $this->assertEquals(1, $int, 'get incorrect int');

        $float = $this->config->{'conf.float'};
        $this->assertEquals(4.2, $float, 'get incorrect float');

        $array = $this->config['conf.array'];
        $this->assertEquals([
            'string' => 'array.string',
            'index 0 value',
            [
                'value' => '123'
            ]
        ], $array, 'get incorrect array');

        $arr0 = $this->config['conf.array.0'];
        $this->assertEquals('index 0 value', $arr0, 'get incorrect in-array value');

        $arr1 = $this->config['conf.array.1.value'];
        $this->assertEquals('123', $arr1, 'get incorrect in-array value');

        $bool = $this->config['conf.bool'];
        $this->assertTrue($bool, 'get incorrect bool value');

        $date = $this->config->get('conf.date');
        $this->assertEquals('2020-02-01', $date, 'get incorrect date');

        $null = $this->config['not exists key'];
        $this->assertNull($null);
    }

    public function testSetConfig()
    {
        $this->config->set('app.0', 'app 0');
        $v1 = $this->config->get('app.0');
        $this->assertEquals('app 0', $v1, 'set failed');

        $this->config->set('array', ['123', 'str' => 'string']);
        $v2 = $this->config->{'array.0'};
        $this->assertEquals('123', $v2);
        $str = $this->config['array.str'];
        $this->assertEquals('string', $str);
    }

    public function testDefault()
    {
        $notExists = $this->config->has('a key');
        $this->assertFalse($notExists, 'not exists');
        $null = $this->config->get('a key');
        $this->assertNull($null, 'not null');
        $default = $this->config->get('a key', 'test');
        $this->assertEquals('test', $default, 'get error default');
    }
}