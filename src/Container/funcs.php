<?php
if (!function_exists('filter_map')) {
    function filter_map(array $arr, ?callable $fn = null, mixed $condition = null): array
    {
        $fn ??= fn ($value, $_key) => $value;

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
    function first(array $arr, callable $fn = null)
    {
        $result = array_filter($arr, $fn, ARRAY_FILTER_USE_BOTH);
        reset($result);
        return current($result);
    }
}

if (!function_exists('convert_extra_type')) {
    function convert_extra_type(string $type, $arg)
    {
        return match ($type) {
            'int' => (int)$arg,
            'string' => (string)$arg,
            'float' => (float)$arg,
            'array' => (array)$arg,
            'bool' => match (strtolower($arg)) {
                'null', null, 'false', false, '0', 0, [], '0.0', .0 => false,
                default => true,
            },
            default => $arg,
        };
    }
}
