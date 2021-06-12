<?php


namespace Xycc\Winter\Validator\Contracts;


use Xycc\Winter\Http\Request\Request;

interface RuleInterface
{
    public function validate($data, Request $request, $attribute): bool;

    public function message(): string;

    public function name(): string;
}