<?php


namespace Xycc\Winter\Container\Proxy;


use Error;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\ClassLoader;
use Xycc\Winter\Container\Exceptions\CannotProxyFinalException;
use Xycc\Winter\Container\Factory\BeanInfo;
use Xycc\Winter\Contract\Attributes\Bean;

#[Bean]
class ProxyManager
{
    private Parser $parser;

    public function __construct(private Application $app, private ClassLoader $loader)
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
    }

    /**
     * 生成id或者type的代理对象
     *
     * @return object
     */
    public function generate(BeanInfo $info, AbstractBeanDefinition $def, bool $hasType): object
    {
        // 如果 bean 有📃存在， 且不是 final 类， 则生成代理类
        if ($def->getFile() !== null && $def->canProxy()) {
            $object = $this->generateLazyProxy($def->getFile());
        } elseif ($hasType === false) {
            // 否则生成匿名代理类， 原依赖注入处不得有类型标注， 类型会不匹配
            $object = $this->generateClosureProxy();
        } else {
            throw new CannotProxyFinalException('不能为扩展里的类、final类生成代理对象，可以考虑去掉类型标注');
        }
        /**@var LazyObject $object */
        $object::class::__initLazyObject__($info);
        return $object;
    }

    protected function getNs($ast): ?Namespace_
    {
        return current(array_filter($ast, fn ($declaration) => $declaration instanceof Namespace_)) ?: null;
    }

    protected function getClassName($ast): string
    {
        $ns = $this->getNs($ast);
        $stmts = $ast;
        if ($ns) {
            $stmts = $ns->stmts;
        }
        $class = current(array_filter($stmts, fn ($declaration) => $declaration instanceof Class_));
        return $class->name->name;
    }

    protected function generateLazyProxy(string $filePath): object
    {
        try {
            $ast = $this->parser->parse(file_get_contents($filePath));
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new MethodNodeVisitor());
            $ast = $traverser->traverse($ast);
            $className = $this->getClassName($ast);

            $printer = new Standard();
            $code = $printer->prettyPrintFile($ast);
            $path = $this->app->getPath('runtime/proxy');
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            $fileName = $path . '/' . $className . '.php';
            file_put_contents($fileName, $code);
            $this->registerAutoload($fileName, $className);
            return new $className();
        } catch (Error $error) {
            throw new RuntimeException($error->getMessage());
        }
    }

    private function registerAutoload(string $filePath, string $className)
    {
        $this->loader->addClassMap([$className => $filePath]);
        $this->loader->register();
    }

    /**
     * 闭包没有类型继承，直接返回一个匿名类
     *
     * @return object
     */
    protected function generateClosureProxy(): object
    {
        return new class {
            use LazyObject;
        };
    }
}