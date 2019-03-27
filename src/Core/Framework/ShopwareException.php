<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

interface ShopwareException extends \Throwable
{
    public function getErrorCode(): string;

    public function getParameters(): array;
}
