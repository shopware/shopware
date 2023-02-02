<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductExportProductCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    protected Criteria $criteria;

    protected ProductExportEntity $productExport;

    protected ExportBehavior $exportBehaviour;

    protected SalesChannelContext $salesChannelContext;

    public function __construct(Criteria $criteria, ProductExportEntity $productExport, ExportBehavior $exportBehavior, SalesChannelContext $salesChannelContext)
    {
        $this->criteria = $criteria;
        $this->productExport = $productExport;
        $this->exportBehaviour = $exportBehavior;
        $this->salesChannelContext = $salesChannelContext;
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
