<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldException;

class FieldExceptionStack
{
    private $exceptions = [];

    public function add(WriteFieldException $exception)
    {
        $this->exceptions[] = $exception;
    }

    public function tryToThrow()
    {
        $exceptions = $this->exceptions;
        $this->exceptions = [];

        if ($exceptions) {
            throw new WriteStackException(...$exceptions);
        }
    }
}
