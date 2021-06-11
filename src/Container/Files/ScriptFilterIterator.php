<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\Files;


use RecursiveFilterIterator;
use RecursiveIterator;
use SplFileInfo;
use Xycc\Winter\Container\FileIterator;

class ScriptFilterIterator extends RecursiveFilterIterator
{
    public function __construct(RecursiveIterator $iterator)
    {
        parent::__construct($iterator);
    }

    public function accept()
    {
        $file = $this->current();
        /**@var SplFileInfo $file */
        if ($file->isDir()) {
            return true;
        }
        if ($file->getExtension() !== 'php') {
            return false;
        }
        $className = FileIterator::getClassName($file);
        return class_exists($className) && !trait_exists($className);

    }
}