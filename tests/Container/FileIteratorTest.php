<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Container;


use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Xycc\Winter\Container\FileIterator;

class FileIteratorTest extends TestCase
{
    public function testIter()
    {
        $result = FileIterator::getFiles(__DIR__, __NAMESPACE__, []);
        $selfFileName = 'FileIteratorTest.php';

        $filtered = array_filter($result, fn (SplFileInfo $file) => $file->getFilename() === $selfFileName);
        $this->assertTrue(count($filtered) === 0);
    }
}