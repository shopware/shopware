<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldException;

use Shopware\Core\Framework\ShopwareException;

abstract class WriteFieldException extends \DomainException implements ShopwareException
{
    abstract public function getPath(): string;

    abstract public function getConcern(): string;

    abstract public function toArray(): array;
}
