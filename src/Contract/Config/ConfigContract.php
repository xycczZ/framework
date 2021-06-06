<?php


namespace Xycc\Winter\Contract\Config;


interface ConfigContract
{
    public function get(string $key, $default = null);

    public function set(string $key, $value);

    public function has(string $key): bool;

    public function merge(array $values);
}