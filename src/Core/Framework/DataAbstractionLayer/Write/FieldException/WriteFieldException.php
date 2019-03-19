<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\ShopwareHttpException;

abstract class WriteFieldException extends ShopwareHttpException
{
    abstract public function getPath(): string;

    abstract public function getConcern(): string;

    abstract public function toArray(): array;

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_FIELD_ERROR';
    }
}
