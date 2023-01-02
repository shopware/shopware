<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('customer-order')]
class SalesChannelDomainNotFoundException extends ShopwareHttpException
{
    public function __construct(SalesChannelEntity $salesChannel)
    {
        parent::__construct(
            'No domain found for sales channel {{ salesChannel }}',
            ['salesChannel' => $salesChannel->getTranslation('name')]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SALES_CHANNEL_DOMAIN_NOT_FOUND';
    }
}
