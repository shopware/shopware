<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductExportServiceInterface
{
    public function get(
        string $fileName,
        string $accessKey,
        SalesChannelContext $salesChannelContext
    ): ProductExportEntity;
}
