<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductExporterInterface
{
    public function generate(
        SalesChannelContext $context,
        ExportBehavior $behavior,
        ?string $productExportId = null
    ): void;

    public function getFilePath(ProductExportEntity $productExport): string;
}
