<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('sales-channel')]
class AlreadyLockedException extends ShopwareHttpException
{
    public function __construct(SalesChannelContext $salesChannelContext)
    {
        parent::__construct('Cannot acquire lock for sales channel {{salesChannelId}} and language {{languageId}}', [
            'salesChannelId' => $salesChannelContext->getSalesChannel()->getId(),
            'languageId' => $salesChannelContext->getLanguageId(),
        ]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_ALREADY_LOCKED';
    }
}
