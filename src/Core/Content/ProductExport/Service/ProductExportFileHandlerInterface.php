<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;

interface ProductExportFileHandlerInterface
{
    public function getFilePath(ProductExportEntity $productExport, bool $partialGeneration = false): string;

    public function writeProductExportResult(ProductExportResult $productExportResult, string $filePath, bool $append = false): bool;

    public function isValidFile(string $filePath, ExportBehavior $behavior, ProductExportEntity $productExport): bool;
}
