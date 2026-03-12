<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
readonly class ProductExportProvisioningContext
{
    public function __construct(
        public SalesChannelEntity $salesChannel,
        public SalesChannelEntity $storefrontSalesChannel,
        public SalesChannelDomainEntity $storefrontDomain,
        public ProductStreamEntity $productStream
    ) {
    }
}
