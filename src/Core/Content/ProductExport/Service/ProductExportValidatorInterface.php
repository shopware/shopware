<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

/**
 * @package sales-channel
 */
interface ProductExportValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array;
}
