<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldException;

class FieldExceptionStack
{
    private $exceptions = [];

    public function add(WriteFieldException $exception): void
    {
        $this->exceptions[] = $exception;
    }

    public function tryToThrow(): void
    {
        $exceptions = $this->exceptions;
        $this->exceptions = [];

        if ($exceptions) {
            throw new WriteStackException(...$exceptions);
        }
    }
}
