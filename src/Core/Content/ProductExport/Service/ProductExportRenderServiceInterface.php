<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductExportRenderServiceInterface
{
    public function renderHeader(
        ProductExportEntity $productExportEntity,
        SalesChannelContext $salesChannelContext
    ): string;

    public function renderFooter(
        ProductExportEntity $productExportEntity,
        SalesChannelContext $salesChannelContext
    ): string;

    public function renderBody(
        ProductExportEntity $productExportEntity,
        EntityCollection $productCollection,
        SalesChannelContext $salesChannelContext
    ): string;

    public function renderProduct(
        ProductExportEntity $productExportEntity,
        SalesChannelProductEntity $productEntity,
        SalesChannelContext $salesChannelContext
    ): string;
}
