<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('sales-channel')]
class SalesChannelDomainNotFoundException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Sales channel domain with ID {{ id }} not found', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_SALES_CHANNEL_DOMAIN_NOT_FOUND';
    }
}
