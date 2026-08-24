<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.8.0 - Will be removed, it is no longer thrown. Catch SitemapAlreadyLockedException instead.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class AlreadyLockedException extends ShopwareHttpException
{
    public function __construct(SalesChannelContext $salesChannelContext)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', SitemapAlreadyLockedException::class)
        );

        parent::__construct('Cannot acquire lock for sales channel {{salesChannelId}} and language {{languageId}}', [
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'languageId' => $salesChannelContext->getLanguageId(),
        ]);
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', SitemapAlreadyLockedException::class)
        );

        return 'CONTENT__SITEMAP_ALREADY_LOCKED';
    }
}
