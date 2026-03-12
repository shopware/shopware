<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
abstract class AbstractProductExportProvider
{
    abstract public function supportsSalesChannelType(string $salesChannelTypeId): bool;

    abstract public function getTechnicalName(): string;

    abstract public function getDefaultTemplate(ProductExportProvisioningContext $context): ProductExportTemplate;

    /**
     * @param array<string, mixed> $renderContext
     *
     * @return array<string, mixed>
     */
    abstract public function extendRenderContext(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $renderContext
    ): array;
}
