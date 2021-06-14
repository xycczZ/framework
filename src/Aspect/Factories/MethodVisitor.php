<?php


namespace Xycc\Winter\Aspect\Factories;


use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use Xycc\Winter\Contract\Attributes\Autowired;
use Xycc\Winter\Contract\Attributes\NoProxy;

class MethodVisitor extends NameResolver
{
    public array $weavingMethods = [];
    public string $id;

    /**
     * 命名空间去掉
     * 类名随机后缀
     * 构造方法改成空
     * 加继承
     * 其他所有都不变
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $node->stmts[] = new Node\Stmt\TraitUse([new Node\Name('\\' . Weaving::class)]);
            $oldClass = $node->name->name;
            if ($node->namespacedName) {
                $node->extends = new Node\Name\FullyQualified($node->namespacedName->toString());
            } else {
                $node->extends = new Node\Name($oldClass);
            }

            $node->attrGroups[] = new Node\AttributeGroup([new Node\Attribute(new Node\Name('\\' . NoProxy::class))]);

            $node->name->name .= uniqid('__weaving__proxy__');
            $node->flags = Node\Stmt\Class_::MODIFIER_FINAL;

            $node->stmts[] = new Node\Stmt\ClassConst([
                new Node\Const_('__ID__', new Node\Scalar\String_($this->id)),
            ]);
            $node->stmts[] = new Node\Stmt\Property(
                Node\Stmt\Class_::MODIFIER_PRIVATE,
                [new Node\Stmt\PropertyProperty('__FACTORY__')],
                [],
                new Node\Name('\\' . ProxyFactory::class),
                [new Node\AttributeGroup([new Node\Attribute(new Node\Name('\\' . Autowired::class))])]
            );
        } elseif ($node instanceof Node\Stmt\ClassMethod && in_array($node->name->name, $this->weavingMethods) && !$node->isFinal() && !$node->isStatic()) {
            if ($node->isMagic()) {
                return $node;
            }
            if ($node->returnType instanceof Node\Name && count($node->returnType?->parts ?: []) === 1 && strtolower($node->returnType?->parts[0]) === 'self') {
                $node->returnType = new Node\Name('parent', $node->returnType->getAttributes());
            }

            $vars = array_map(fn ($param) => new Node\Arg($param->var, byRef: $param->byRef, unpack: $param->variadic), $node->getParams());
            $node->stmts = [
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        new Node\Expr\Variable('closure'),
                        new Node\Expr\MethodCall(
                            new Node\Expr\Variable('this'),
                            '__getProxyClosure__',
                            [
                                new Node\Arg(new Node\Scalar\String_($node->name->name)),
                            ]
                        )
                    ),
                ),
                new Node\Stmt\Return_(
                    new Node\Expr\FuncCall(
                        new Node\Expr\Variable('closure'),
                        [
                            new Node\Arg(
                                new Node\Expr\ArrowFunction(
                                    [
                                        'expr' => new Node\Expr\StaticCall(
                                            new Node\Name('parent'),
                                            $node->name->name,
                                            $vars
                                        ),
                                    ]
                                )
                            ),
                        ],
                    )
                ),
            ];
        } elseif ($node instanceof Node\Stmt\Property) {
            return NodeTraverser::REMOVE_NODE;
        }

        return $node;
    }
}