<?php
declare(strict_types=1);

namespace Xycc\Winter\Container\Files;


use RecursiveFilterIterator;
use RecursiveIterator;
use ReflectionAttribute;
use ReflectionClass;
use SplFileInfo;
use Xycc\Winter\Container\FileIterator;
use Xycc\Winter\Contract\Attributes\Bean;

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
        //return class_exists($className);
        if (!class_exists($className)) {
            return false;
        }

        //$ref = new ReflectionClass($className);
        //return count($ref->getAttributes(Bean::class, ReflectionAttribute::IS_INSTANCEOF)) > 0;
        return true;
    }
}