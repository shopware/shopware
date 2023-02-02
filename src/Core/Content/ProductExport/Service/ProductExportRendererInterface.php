<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductExportRendererInterface
{
    public function renderHeader(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string;

    public function renderFooter(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string;

    public function renderBody(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $data
    ): string;
}
