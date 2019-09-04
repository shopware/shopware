<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductExportServiceInterface
{
    public function generate(
        SalesChannelContext $context,
        ?string $productExportId = null,
        bool $includeInactive = false,
        bool $ignoreCache = false
    ): void;

    public function generateExport(
        ProductExportEntity $productExport,
        SalesChannelContext $context,
        bool $ignoreCache = false
    ): void;

    public function convertEncoding(string $content, string $encoding): string;
}
