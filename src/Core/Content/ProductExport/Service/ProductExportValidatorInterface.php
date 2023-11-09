<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\Error\Error;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductExportValidatorInterface
{
    /**
     * @return list<Error>
     */
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array;
}
