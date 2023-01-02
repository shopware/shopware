<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\ProductExport\ProductExportEntity;

/**
 * @package inventory
 */
#[Package('inventory')]
interface ProductExportValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array;
}
