<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;

interface ValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void;
}
