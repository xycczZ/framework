<?php
declare(strict_types=1);

namespace Xycc\Winter\Container;


use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Xycc\Winter\Container\Files\ScriptFilterIterator;

class FileIterator
{
    private static string $directory;
    private static string $ns;

    public static function getFiles(string $directory, string $ns, array $exclude): array
    {
        self::$directory = $directory;
        self::$ns = $ns;

        $directory = realpath($directory);
        $dirIter = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::KEY_AS_PATHNAME
            | FilesystemIterator::CURRENT_AS_FILEINFO
        );

        $filterIter = new ScriptFilterIterator($dirIter);
        $iter = new RecursiveIteratorIterator($filterIter);
        $files = iterator_to_array($iter);
        return array_filter($files, function (SplFileInfo $file) use ($exclude) {
            if ($file->isDir()) {
                return false;
            }

            foreach ($exclude as $item) {
                if (is_dir($item) && str_starts_with($item, $file->getRealPath())) {
                    return false;
                }
                if (is_file($item) && $file->getRealPath() === $item) {
                    return false;
                }
            }
            return true;
        });
    }

    public static function getClassName(SplFileInfo $file): string
    {
        $fileName = $file->getFilename();
        $name = substr($fileName, 0, strlen($fileName) - strlen('.php'));

        $namespace = self::getSubNs($file->getPath());
        return $namespace . '\\' . $name;
    }

    protected static function getSubNs(string $path): string
    {
        $subPath = substr($path, strlen(self::$directory));
        $subNs = str_replace('/', '\\', $subPath);
        return self::$ns . $subNs;
    }
}