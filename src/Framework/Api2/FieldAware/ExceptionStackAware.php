<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\FieldException\FieldExceptionStack;

interface ExceptionStackAware
{
    public function setExceptionStack(FieldExceptionStack $exceptionStack): void;
}