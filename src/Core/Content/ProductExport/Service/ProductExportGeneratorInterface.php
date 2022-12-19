<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;

/**
 * @package inventory
 */
interface ProductExportGeneratorInterface
{
    public function generate(
        ProductExportEntity $productExport,
        ExportBehavior $exportBehavior
    ): ?ProductExportResult;
}
