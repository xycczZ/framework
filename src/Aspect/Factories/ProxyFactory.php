<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Factories;


use Closure;
use Error;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionMethod;
use RuntimeException;
use SplFileInfo;
use Xycc\Winter\Aspect\Attributes\Aspect;
use Xycc\Winter\Aspect\Expressions\Expression;
use Xycc\Winter\Aspect\Processors\ProxyProcessor;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Container\BeanDefinitions\AbstractBeanDefinition;
use Xycc\Winter\Container\ClassLoader;
use Xycc\Winter\Contract\Attributes\Bean;


#[Bean]
class ProxyFactory
{
    private array $pointcutAdviseMap;
    private array $processors;
    private array $processorMap = [];
    private Parser $parser;

    public function __construct(
        private Application $app,
        private BeanDefinitionCollection $collection,
        private ClassLoader $loader,
    )
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
    }

    public function setPointcutAdviseMap(array $map)
    {
        $this->pointcutAdviseMap = $map;
    }

    public function setProcessors(array $processors)
    {
        $this->processors = $processors;
    }

    public function weaveIn()
    {
        $this->collectBeanProcessors();
        $this->proxy();
    }

    protected function collectBeanProcessors()
    {
        // 有类型的， 非Final的， 可以实例化的才可以切入
        $defs = $this->collection->filterDefinitions(
            fn (AbstractBeanDefinition $def) => $def->getFile() !== null &&
                !$def->classHasAttribute(Aspect::class) &&
                $def->getClassName() !== null &&
                !$def->getRefClass()->isFinal() &&
                $def->getRefClass()->isInstantiable() &&
                !$def->getRefClass()->isAnonymous()
        );

        foreach ($this->pointcutAdviseMap as $expression => $aspectIdAndAdvises) {
            $expr = new Expression($expression);

            if ($expr->isMatchAll()) {
                foreach ($defs as $def) {
                    foreach ($def->getRefMethod() as $refMethod) {
                        $this->insertProcessorMap($def->getId(), $refMethod->name, $this->collectProcessors($aspectIdAndAdvises, $refMethod));
                    }
                }
                continue;
            }

            $defss = array_filter($defs, fn (AbstractBeanDefinition $def) => $expr->matchClass($def->getClassName()));
            foreach ($defss as $def) {
                foreach ($def->getRefMethod() as $refMethod) {
                    /**@var ReflectionMethod $refMethod */
                    if ($expr->matchAccess($refMethod->getModifiers()) && $expr->matchMethod($refMethod) && $expr->matchReturnType($refMethod->getReturnType())) {
                        $this->insertProcessorMap($def->getId(), $refMethod->name, $this->collectProcessors($aspectIdAndAdvises, $refMethod));
                    }
                }
            }
        }
    }

    protected function insertProcessorMap(string $id, string $method, array $processors)
    {
        $this->processorMap[$id][$method] ??= [];
        $this->processorMap[$id][$method] = array_merge(
            $this->processorMap[$id][$method],
            $processors
        );
    }

    protected function collectProcessors(array $aspectIdAndAdvises, ReflectionMethod $originMethod)
    {
        $data = array_map(fn (array $aspectIdAndAdvise) => array_map(
            fn (string $advise) => $this->processors[$aspectIdAndAdvise['aspectClass']][$advise]->setOriginMethod($originMethod),
            $aspectIdAndAdvise['advises']
        ), $aspectIdAndAdvises);
        $result = [];
        foreach ($data as $value) {
            $result = array_merge($result, $value);
        }
        return $result;
    }

    /**
     * 为原有的方法生成代理， 每个需要织入的方法都改成
     * modifier function name($originParams) {
     *  $closure = $this->__FACTORY__->getProxy(self::__ID__, 'name');
     *  return $closure(fn () => parent::name($originParams));
     * }
     * 然后将原来的BeanDefinition的file信息改成新的文件， class信息也改成新的类
     */
    public function proxy()
    {
        foreach ($this->processorMap as $id => $data) {
            $def = $this->collection->findDefinitionById($id);
            $methods = array_keys($data);
            $this->generateProxy($def, $methods);
        }
    }

    public function getProxy(string $id, string $method)
    {
        $processors = $this->processorMap[$id][$method];
        return array_reduce(array_reverse($processors), $this->carry(), fn ($passable) => $passable());
    }

    protected function generateProxy(AbstractBeanDefinition $def, array $methods)
    {
        $file = $def->getFile();
        try {
            $ast = $this->parser->parse(file_get_contents($file->getRealPath()));
            $traverser = new NodeTraverser();
            $visitor = new MethodVisitor();
            $visitor->weavingMethods = $methods;
            $visitor->id = $def->getId();
            $traverser->addVisitor($visitor);
            $ast = $traverser->traverse($ast);

            [$ns, $className] = $this->getNamespacedClassName($ast);
            $fqn = $ns ? $ns . '\\' . $className : $className;

            $printer = new Standard();
            $code = $printer->prettyPrintFile($ast);
            $path = $this->app->getPath('runtime/weaving');
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $fileName = $path . '/' . $className . '.php';
            file_put_contents($fileName, $code);
            $this->loader->addClassMap([$fqn => $fileName]);
            $this->loader->register();

            $def->reload(new SplFileInfo($fileName), $fqn);
        } catch (Error $error) {
            throw new RuntimeException($error->getMessage());
        }
    }

    protected function getNamespacedClassName($ast): array
    {
        /**@var Namespace_ $nsAst */
        $nsAst = current(array_filter($ast, fn ($declaration) => $declaration instanceof Namespace_)) ?: null;
        $namespace = $nsAst?->name->toString() ?: '';

        $stmts = $nsAst?->stmts ?: $ast;

        $class = current(array_filter($stmts, fn ($declaration) => $declaration instanceof Class_));
        return [$namespace, $class->name->name];
    }


    private function carry(): callable
    {
        return function (Closure $stack, ProxyProcessor $pipe) {
            return function (Closure $passable) use ($stack, $pipe) {
                return $pipe->proxy($passable, $stack);
            };
        };
    }
}