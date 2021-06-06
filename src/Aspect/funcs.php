<?php
if (! function_exists('contains')) {
    function contains(string $haystack, string $needle): bool
    {
        if (! str_contains($needle, '*')) {
            return str_contains($haystack, $needle);
        }
        $pattern = '#'.str_replace('*', '.*?', $needle).'#';
        return !!preg_match($pattern, $haystack);
    }
}

if (!function_exists('wildcard')) {
    function wildcard(string $haystack, string $needle): bool
    {
        if (! str_contains($needle, '*')) {
            return $haystack === $needle;
        }

        $pattern = '#' . str_replace('*', '.*?', $needle) . '#';
        return !!preg_match($pattern, $haystack);
    }
}

if (!function_exists('deep_map')) {
    function deep_map(array $arr, callable $fn): array
    {
        $arr = array_map($fn, $arr);
        $result = [];
        foreach ($arr as $value) {
            foreach ($value as $key => $item) {
                $result[$key][] = $item;
            }
        }

        return $result;
    }
}