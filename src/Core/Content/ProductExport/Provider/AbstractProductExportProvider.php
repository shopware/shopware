<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
abstract class AbstractProductExportProvider
{
    abstract public function getTechnicalName(): string;

    /**
     * @return array{headerTemplate: string, bodyTemplate: string, footerTemplate: string}
     */
    abstract public function getDefaultTemplateContent(): array;

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
