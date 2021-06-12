<?php


namespace Xycc\Winter\Validator;


use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Components\AttributeParser;
use Xycc\Winter\Validator\Attributes\Validation;

#[Component, NoProxy]
class Validator
{
    /**
     * 验证数据，验证通过就赋值给这个对象
     * 验证数据， 解析对象的所有字段注解 ？？？ 一定要用字段吗
     * 结合laravel的orm， 标在request上还是直接标注在model上？
     * @see Validation
     */
    public function validate(array $data, object $entity)
    {
        $attrs = AttributeParser::parseClass($entity::class);
    }
}