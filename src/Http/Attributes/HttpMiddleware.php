<?php


namespace Xycc\Winter\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class HttpMiddleware extends Middleware
{

}