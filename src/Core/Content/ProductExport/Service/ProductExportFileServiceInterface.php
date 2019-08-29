<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

interface ProductExportFileServiceInterface
{
    public function getFilePath(ProductExportEntity $productExportEntity): string;

    public function getDirectory(): string;
}
