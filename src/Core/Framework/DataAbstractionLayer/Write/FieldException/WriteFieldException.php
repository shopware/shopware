<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\ShopwareException;

interface WriteFieldException extends ShopwareException
{
    public function getPath(): string;
}
