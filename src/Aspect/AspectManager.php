<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect;


use PhpParser\Parser;
use PhpParser\ParserFactory;
use Xycc\Winter\Aspect\Exceptions\NotFoundFileException;
use Xycc\Winter\Aspect\Processors\ProxyProcessor;
use Xycc\Winter\Container\Application;
use Xycc\Winter\Container\BeanDefinitionCollection;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\Bean;

#[Bean]
class AspectManager
{
    #[Autowired]
    private Application $app;

    #[Autowired('beanManager')]
    private BeanDefinitionCollection $collection;

    /**
     * @var ProxyProcessor[]
     */
    private array $processors = [];

    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
    }

    public function start()
    {
        $this->collectAspect();
    }

    protected function collectAspect()
    {

    }

    /**
     * 为指定的 class 生成代理类
     * 要织入的类，方法
     */
    public function proxy(string $class, string $method, string $aspectClass)
    {
        $definition = $this->collection->findDefinition($class);
        if ($definition === null) {
            throw new NotFoundFileException($class);
        }
        $ast = $this->parser->parse($definition->getFile()->getRealPath());
        $attrs = $definition->getAllMethodAttributes()[$method];
        foreach ($this->processors as $processor) {
            if ($processor->shouldProxy($attrs)) {
                $ast = $processor->proxy();
            }
        }
    }

    public function setProcessors(array $processors)
    {
        $this->processors = $processors;
    }

    public function appendProcessors(ProxyProcessor ...$processors)
    {
        $this->processors = array_merge($this->processors, $processors);
    }

    public function prependProcessors(ProxyProcessor ...$processors)
    {
        $this->processors = array_merge($processors, $this->processors);
    }
}