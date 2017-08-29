<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldException;

abstract class ApiFieldException extends \DomainException
{
    abstract public function getPath(): string;

    abstract public function getConcern(): string;

    abstract public function toArray(): array;
}