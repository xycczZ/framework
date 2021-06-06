<?php


namespace Xycc\Winter\Core\Exceptions;


use RuntimeException;
use Xycc\Winter\Http\Request\Request;

class ApplicationException extends RuntimeException
{
    public function render(Request $request)
    {
        return [
            'url' => $request->getUri(),
            'msg' => $this->message,
            'status' => 500,
        ];
    }
}