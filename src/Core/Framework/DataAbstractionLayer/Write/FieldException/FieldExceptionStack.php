<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

class FieldExceptionStack
{
    private $exceptions = [];

    public function add(WriteFieldException ...$exceptions): void
    {
        foreach ($exceptions as $exception) {
            $this->exceptions[] = $exception;
        }
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
