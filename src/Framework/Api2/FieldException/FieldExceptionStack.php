<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldException;

class FieldExceptionStack
{
    private $exceptions = [];

    public function add(ApiFieldException $exception)
    {
        $this->exceptions[] = $exception;
    }

    public function tryToThrow()
    {
        $exceptions = $this->exceptions;
        $this->exceptions = [];

        if($exceptions) {
            throw new ApiStackException(... $exceptions);
        }
    }
}