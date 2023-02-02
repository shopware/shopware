<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class InvalidSitemapKey extends ShopwareHttpException
{
    public function __construct(string $sitemapKey)
    {
        parent::__construct('Invalid sitemap config key: "{{ sitemapKey }}"', ['sitemapKey' => $sitemapKey]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_INVALID_KEY';
    }
}
