<?php


namespace Xycc\Winter\Container\Proxy;


use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use Xycc\Winter\Container\Exceptions\CannotProxyFinalException;

class MethodNodeVisitor extends NameResolver
{
    private const LAZY_METHOD = '__callOriginMethodAndReplaceSelf__';

    /**
     * Final类不能生成代理
     * public, protected的普通方法全部替换成调用LazyObject的方法
     * 命名空间去掉
     * 类名加随机后缀
     * 构造方法改成空
     * magic方法保持不变
     * 加Final
     * 加继承
     * 删去类的Attribute
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->isFinal()) {
                throw new CannotProxyFinalException('不能生成final类的代理');
            }
            $node->stmts[] = new Node\Stmt\TraitUse([new Node\Name(LazyObject::class)]);
            $oldClassName = $node->name->name;
            if ($node->namespacedName) {
                $node->extends = new Node\Name\FullyQualified($node->namespacedName->toString());
            } else {
                $node->extends = new Node\Name($oldClassName);
            }

            $node->namespacedName = null;
            $node->name->name .= uniqid('__proxy__');
            $node->flags = Node\Stmt\Class_::MODIFIER_FINAL;
            $node->attrGroups = [];
        } elseif ($node instanceof Node\Stmt\ClassMethod && (!$node->isPrivate() || !$node->isMagic())) {
            if ($node->name->name === '__construct') {
                $node = new Node\Stmt\ClassMethod(new Node\Identifier('__construct'), [
                    'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC,
                ]);
                return $node;
            } elseif ($node->isStatic()) {
                return NodeTraverser::REMOVE_NODE;
            }
            if ($node->returnType instanceof Node\Name && count($node->returnType?->parts ?: []) === 1 && strtolower($node->returnType?->parts[0]) === 'self') {
                $node->returnType = new Node\Name('parent', $node->returnType->getAttributes());
            }
            $vars = array_map(fn ($param) => new Node\Arg($param->var, byRef: $param->byRef, unpack: $param->variadic), $node->getParams());
            $node->stmts = [
                new Node\Stmt\Return_(
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable('this'),
                        new Node\Identifier(self::LAZY_METHOD),
                        [new Node\Scalar\String_($node->name->name), ...$vars],
                    )
                ),
            ];
        } elseif ($node instanceof Node\Stmt\Namespace_) {
            $node->setAttribute('kind', Node\Stmt\Namespace_::KIND_SEMICOLON);
            $node->name = null;
        } elseif ($node instanceof Node\Stmt\Property) {
            return NodeTraverser::REMOVE_NODE;
        } elseif ($node instanceof Node\Stmt\Use_) {
            // suppress warning
            $node->uses = array_filter($node->uses, fn (Node\Stmt\UseUse $use) => $use->alias !== null);
            if (count($node->uses) === 0) {
                return NodeTraverser::REMOVE_NODE;
            }
            return $node;
        }
        return $node;
    }
}