<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('sales-channel')]
class ProductExportProductCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    public function __construct(
        protected Criteria $criteria,
        protected ProductExportEntity $productExport,
        protected ExportBehavior $exportBehaviour,
        protected SalesChannelContext $salesChannelContext
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getProductExport(): ProductExportEntity
    {
        return $this->productExport;
    }

    public function getExportBehaviour(): ExportBehavior
    {
        return $this->exportBehaviour;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
