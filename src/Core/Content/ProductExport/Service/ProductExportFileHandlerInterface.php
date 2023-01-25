<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
interface ProductExportFileHandlerInterface
{
    public function getFilePath(ProductExportEntity $productExport, bool $partialGeneration = false): string;

    public function writeProductExportContent(string $content, string $filePath, bool $append = false): bool;

    public function isValidFile(string $filePath, ExportBehavior $behavior, ProductExportEntity $productExport): bool;

    public function finalizePartialProductExport(string $partialFilePath, string $finalFilePath, string $headerContent, string $footerContent): bool;
}
