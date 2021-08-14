<?php
if (!function_exists('filter_map')) {
    function filter_map(array $arr, ?callable $fn = null, mixed $condition = null): array
    {
        $fn ??= fn($value, $_key) => $value;

        $result = [];
        foreach ($arr as $key => $value) {
            $item = $fn($value, $key);
            if ($item !== $condition) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}

if (!function_exists('first')) {
    function first(array $arr, callable $fn = null): mixed
    {
        $result = array_filter($arr, $fn, ARRAY_FILTER_USE_BOTH);
        reset($result);
        return current($result) ?: null;
    }
}

if (!function_exists('convert_extra_type')) {
    function convert_extra_type(string $type, mixed $arg): mixed
    {
        return match ($type) {
            'int' => (int)$arg,
            'string' => (string)$arg,
            'float' => (float)$arg,
            'array' => (array)$arg,
            'bool' => match ($arg) {
                null, false, 0, [], .0 => false,
                is_string($arg) => match (strtolower($arg)) {
                    'null', 'false', '0', '0.0' => false,
                    default => true,
                },
                default => true,
            },
            default => $arg,
        };
    }
}

if (!function_exists('flatten_map')) {
    function flatten_map(array $arr, callable $func = null, int $depth = INF): array
    {
        $keys = array_keys($arr);
        $arr  = array_map($func, $arr, $keys);
        do {
            $result = [];
            foreach ($arr as $item) {
                if (is_array($item)) {
                    $result = array_merge($result, $item);
                } else {
                    $result[] = $item;
                }
            }
            $arr    = $result;
            $hasArr = count(array_filter($arr, fn($item) => is_array($item))) > 0;
        } while ($depth <= 0 || !$hasArr);

        return $arr;
    }
}
