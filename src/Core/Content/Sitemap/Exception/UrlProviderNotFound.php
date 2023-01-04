<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('sales-channel')]
class UrlProviderNotFound extends ShopwareHttpException
{
    public function __construct(string $provider)
    {
        parent::__construct('provider "{{ provider }}" not found.', ['provider' => $provider]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_PROVIDER_NOT_FOUND';
    }
}
