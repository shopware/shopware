<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

interface ProductExportRenderServiceInterface
{
    public function renderHeader(ProductExportEntity $productExportEntity): string;

    public function renderFooter(ProductExportEntity $productExportEntity): string;

    public function renderBody(ProductExportEntity $productExportEntity, EntityCollection $productCollection): string;

    public function renderProduct(ProductExportEntity $productExportEntity, SalesChannelProductEntity $productEntity): string;
}
