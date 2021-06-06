<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Expressions;


use Xycc\Winter\Aspect\Exceptions\InvalidExpressionException;

class ParamExpr extends AbstractExpr
{
    private bool $matchAllType = false;
    private bool $matchAllName = false;
    private bool $matchAllDefault = false;
    private array $types = [];
    private string $name = '';
    private ?string $default = null; // 默认 null， 表达式中没有设定默认值

    /**
     * 由于表达式其实只是字符串，默认值要么为通配，要么是标量类型， 不认识的值当做字符串处理
     * 匹配比较的时候，表达式设置的默认值和方法的默认值用 两个==匹配
     * 表达式中如果有指定变量名，变量名必须带$前缀，就算是单个*号的通配符，也要带上 $*
     */
    public function parse(string $expr): void
    {
        $this->expr = trim($expr);
        if ($expr === '*') {
            $this->matchAll = true;
            $this->matchAllType = true;
            return ;
        }

        // 先去掉=号左右的空格
        $rest = preg_replace('~(\s*)=(\s*)~', '=', $expr);
        if (!is_string($rest)) {
             $this->throwException();
        }
        // 检查是否有等号，先把等号后面的默认值处理掉
        if (str_contains($rest, '=')) {
            [$rest, $default] = explode('=', $expr);
            if ($default === '*') {
                $this->matchAllDefault = true;
            } else {
                $this->default = $default;
            }
        }

        // 能去掉的空格都去掉了，如果还有空格存在的话，只能是类型与变量的分隔了
        if (str_contains($rest, ' ')) {
            $segments = preg_split('/\s+/', $rest);

            $this->parseTypes($segments[0]);
            $this->parseName($segments[1]);
        } else {
            // 如果没有空格的话，类型与变量只存在一个， 如果存在$则是变量名指定了，类型没有指定
            if (str_contains($rest, '$')) {
                $this->parseName($rest);
            } else {
                $this->parseTypes($rest);
            }
        }
    }

    private function parseName(string $name): void
    {
        if ($name === '$*') {
            $this->matchAllName = true;
        } else {
            $this->name = substr($name, 1);
        }
    }

    private function parseTypes(string $typeStr): void
    {
        if ($typeStr === '*') {
            $this->matchAllType = true;
        } else {
            // 如果是联合类型的话， 还有|符号
            $types = explode('|', $typeStr);
            $types = array_map('trim', $types);
            $this->types = $types;
        }
    }

    public function match(array $info): bool
    {
        if ($this->matchAll) {
            return true;
        }

        return $this->matchType($info['type']) && $this->matchName($info['name']) && $this->matchDefault($info['default']);
    }

    /**
     * 匹配类型的时候，将实际类型的每一个类型匹配
     * @param array $types
     * @return bool
     */
    private function matchType(?array $types): bool
    {
        if ($this->matchAllType) {
            return true;
        }

        if ($types === null) {
            return count($types) === 0;
        }

        $ok = 0;
        foreach ($types as $type) {
            $inner = $ok;
            foreach ($this->types as $exprType) {
                if (wildcard($type, $exprType)) {
                    $inner++;
                    break ;
                }
            }

            if ($ok === $inner) {
                return false;
            }
            $ok++;
        }

        return true;
    }

    private function matchName(string $name): bool
    {
        return $this->matchAllName || wildcard($name, $this->name);
    }

    private function matchDefault(mixed $default): bool
    {
        return $this->matchAllDefault || $this->default == $default;
    }

    /**
     * @throws InvalidExpressionException
     */
    private function throwException()
    {
        throw new InvalidExpressionException('非法的方法参数表达式: ' . $this->expr);
    }
}