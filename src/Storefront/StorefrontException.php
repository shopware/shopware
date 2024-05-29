<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
class StorefrontException extends HttpException
{
    public static function noActiveCurrency(): self
    {
        return new self(400, 'STOREFRONT__NO_ACTIVE_CURRENCY', 'No active currency found');
    }

    public static function noActiveLanguage(): self
    {
        return new self(400, 'STOREFRONT__NO_ACTIVE_LANGUAGE', 'No active language found');
    }
}
