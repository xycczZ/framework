<?php


namespace Xycc\Winter\Container\Proxy;


use Error;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\ClassLoader;
use Xycc\Winter\Container\Exceptions\CannotProxyFinalException;
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
     * 生成代理类
     * 原类是可以继承的则生成代理类
     * 否则返回null，自行生成匿名类
     *
     * @return ?string
     */
    public function generate(AbstractBeanDefinition $def): ?string
    {
        // 如果 bean 有📃存在， 且不是 final 类， 则生成代理类
        if ($def->canProxy()) {
            return $def->getFile() ? $this->generateLazyProxy($def->getFile()) : $this->generateExtensionProxy($def->getClassName());
        } else {
            throw new CannotProxyFinalException('不能为final类生成代理对象，可以考虑去掉类型标注');
        }
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

    protected function generateLazyProxy(string $filePath): string
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
            return $className;
        } catch (Error $error) {
            throw new RuntimeException($error->getMessage());
        }
    }

    protected function generateExtensionProxy(string $class)
    {
        $ref = new ReflectionClass($class);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        $methods = array_filter($methods, fn (ReflectionMethod $method) => !$method->isFinal() && !$method->isStatic());

        $className = $class . uniqid('__proxy_ext__');

        $methodStubs = [];
        foreach ($methods as $method) {
            $methodStubs[] = $this->prepareMethod($method);
        }

        $content = implode('', $methodStubs);
        $fileContent = <<<FILE
namespace {
    class $className
    {
    $content
    } 
}
FILE;
        $path = $this->app->getPath('runtime/proxy');
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $fileName = $path . '/' . $className . '.php';
        file_put_contents($fileName, $fileContent);
        $this->registerAutoload($fileName, $className);
        return $className;
    }

    protected function prepareMethod(ReflectionMethod $method)
    {
        $args = [];
        $argNames = [];
        foreach ($method->getParameters() as $parameter) {
            $argNames[] = '$' . $parameter->name;
            $typeStub = '';
            if ($parameter->hasType()) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionUnionType) {
                    $argType = array_map(fn (ReflectionNamedType $t) => $t->getName(), $type->getTypes());
                } else {
                    $argType = $type->getName();
                }
                $typeStub = implode('|', $argType) . ' ';
            }

            $args[] = sprintf('%s$%s', $typeStub, $parameter->name);
        }

        $arg = implode(', ', $args);
        $argName = implode(', ', $argNames);

        return <<<METHOD
    public function $method->name($arg)
    {
        return \$this->__callOriginMethodAndReplaceSelf__('$method->name', $argName);
    }

METHOD;

    }

    private function registerAutoload(string $filePath, string $className)
    {
        $this->loader->addClassMap([$className => $filePath]);
        $this->loader->register();
    }
}