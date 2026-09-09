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
class AlreadyLockedException extends SitemapException
{
    public function __construct(SalesChannelContext $salesChannelContext)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::SITEMAP_ALREADY_LOCKED,
            'Cannot acquire lock for sales channel {{salesChannelId}} and language {{languageId}}',
            [
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                'languageId' => $salesChannelContext->getLanguageId(),
            ],
        );
    }
}
