<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UrlProviderNotFound extends ShopwareHttpException
{
    public function __construct(string $provider, ?\Throwable $previous = null)
    {
        parent::__construct('provider "{{ provider }}" not found.', ['provider' => $provider], $previous);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_PROVIDER_NOT_FOUND';
    }
}
