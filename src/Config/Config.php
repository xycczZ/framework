<?php


namespace Xycc\Winter\Config;

use ArrayAccess;
use ArrayIterator;
use DirectoryIterator;
use IteratorAggregate;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Config\ConfigContract;
use Yosymfony\Toml\Toml;

#[Component('config')]
#[NoProxy]
class Config implements ConfigContract, ArrayAccess, IteratorAggregate
{
    private array $config = [];

    #[ArrayShape(['php' => 'SplFileInfo[]', 'toml' => 'SplFileInfo[]', 'ini' => 'SplFileInfo[]', 'yml' => 'SplFileInfo[]'])]
    private array $files = [
        'php' => [],
        'toml' => [],
        'ini' => [],
        'yml' => [],
    ];

    public function all(): array
    {
        return $this->config;
    }

    public function get(string $key, $default = null)
    {
        if (!str_contains($key, '.')) {
            $result = $this->config[$key] ?? $default;
        } else {
            $keys = explode('.', $key);
            $arr = $this->config;
            while (count($keys) > 0) {
                $k = array_shift($keys);
                if (!isset($arr[$k])) {
                    return $default;
                }
                $arr = $arr[$k];
            }
            $result = $arr ?? $default;
        }

        return $this->explain($result);
    }

    private function explain($result)
    {
        if (is_array($result)) {
            return array_map(fn ($item) => $this->explain($item), $result);
        } elseif (!is_string($result)) {
            return $result;
        } else {
            // config value
            $result = preg_replace_callback(
                '/(?<!\\\)\$\{([a-zA-Z][.\w-]*)\}/',
                fn ($match) => $this->get($match[1]),
                $result
            );
            // constant value
            return preg_replace_callback(
                '/(?<!\\\)#\{([a-zA-Z][.\w-]*)\}/',
                fn ($match) => constant($match[1]),
                $result
            );
        }
    }

    public function setArr(array $config)
    {
        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function set(string $key, $value)
    {
        if (!str_contains($key, '.')) {
            $this->config[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $arr = &$this->config;
        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($arr[$k])) {
                $arr[$k] = [];
            }
            $arr = &$arr[$k];
        }

        $arr[array_shift($keys)] = $value;
    }

    public function has(string $key): bool
    {
        if (!str_contains($key, '.')) {
            return isset($this->config[$key]);
        }

        $keys = explode('.', $key);
        $arr = $this->config;
        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($arr[$k])) {
                return false;
            }
            $arr = $arr[$k];
        }

        return isset($arr[array_shift($keys)]);
    }

    public function merge(array $values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function scan(string $rootPath)
    {
        $this->mergeEnv();
        $configPath = "{$rootPath}/config";
        if (!is_dir($configPath)) {
            return;
        }

        $dir = new DirectoryIterator($configPath);

        $this->handleDir($dir);

        $this->parseConfig([...$this->files['php'], ...$this->files['ini'], ...$this->files['toml'], ...$this->files['yml']]);

        $this->overrideEnv($rootPath);
    }

    protected function handleDir(DirectoryIterator $dir)
    {
        while ($dir->valid()) {
            $current = $dir->current();
            if ($current->isFile()) {
                $this->handleFile($current->getFileInfo());
            }
            $dir->next();
        }
    }

    public function overrideEnv(string $rootPath)
    {
        $this->files = ['ini' => [], 'php' => [], 'yml' => [], 'toml' => []];
        $env = $this->get('app.env');
        $envPath = "$rootPath/config/$env";

        if (!is_dir($envPath)) {
            return;
        }

        $dir = new DirectoryIterator($envPath);
        $this->handleDir($dir);

        $this->parseConfig([...$this->files['php'], ...$this->files['ini'], ...$this->files['toml'], ...$this->files['yml']]);
    }

    protected function mergeEnv()
    {
        $env = array_filter($_ENV, fn ($key) => str_starts_with(strtolower($key), 'winter_'), ARRAY_FILTER_USE_KEY);

        foreach ($env as $key => $value) {
            $this->set(substr(strtolower($key), strlen('winter_')), $value);
        }

        if (!isset($this['app.env'])) {
            $this->set('app.env', 'dev');
        }
    }

    /**
     * @param array<string> $files 绝对路径文件数组
     */
    public function parseConfig(array $files)
    {
        foreach ($files as $file) {
            $fileInfo = new SplFileInfo($file);
            switch ($fileInfo->getExtension()) {
                case 'php':
                    $this->parsePHP($fileInfo);
                    break;
                case 'toml':
                    $this->parseToml($fileInfo);
                    break;
                case 'ini':
                    $this->parseIni($fileInfo);
                    break;
                case 'yaml':
                case 'yml':
                    $this->parseYml($fileInfo);
                    break;
            }
        }
    }

    protected function handleFile(SplFileInfo $file)
    {
        switch ($file->getExtension()) {
            case 'php':
                $this->files['php'][] = $file;
                break;
            case 'toml':
                $this->files['toml'][] = $file;
                break;
            case 'ini':
                $this->files['ini'][] = $file;
                break;
            case 'yaml':
            case 'yml':
                $this->files['yml'][] = $file;
                break;
        }
    }

    protected function parsePHP(SplFileInfo $file)
    {
        $result = include $file->getRealPath();
        $config[$file->getBasename('.php')] = $result;
        $this->merge(static::dot($config));
    }

    protected function parseToml(SplFileInfo $file)
    {
        $result = Toml::parseFile($file->getRealPath());
        $config[$file->getBasename('.toml')] = $result;
        $this->merge(static::dot($config));
    }

    protected function parseIni(SplFileInfo $file)
    {
        $result = parse_ini_file($file->getRealPath());
        $config[$file->getBasename('.ini')] = $result;
        $this->merge(static::dot($config));
    }

    protected function parseYml(SplFileInfo $file)
    {
        $result = Yaml::parseFile($file->getRealPath());
        $config[$file->getBasename('.' . $file->getExtension())] = $result;
        $this->merge(static::dot($config));
    }

    public static function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        throw new RuntimeException('不能删除配置');
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }

    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->config);
    }
}