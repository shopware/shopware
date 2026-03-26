<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Base class for Agentic Commerce product export providers.
 *
 * Handles common functionality like tracking.
 * Concrete providers only need to implement {@see buildProviderContext()} for their format-specific fields.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
abstract class AbstractAgenticCommerceProductExportProvider
{
    abstract public function getTechnicalName(): string;

    /**
     * @param array<string, mixed> $renderContext
     *
     * @return array<string, mixed>
     */
    final public function extendRenderContext(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $renderContext,
    ): array {
        $agenticConfig = $salesChannelContext->getSalesChannel()->getConfiguration() ?? [];
        $trackingCodes = $this->resolveTrackingCodes($productExport, $agenticConfig);

        $renderContext['provider'] = new ArrayStruct(array_merge(
            [
                'name' => $this->getTechnicalName(),
                'referralCode' => $salesChannelContext->getSalesChannelId(),
                'affiliateCode' => $trackingCodes['affiliateCode'],
                'campaignCode' => $trackingCodes['campaignCode'],
            ],
            $this->buildProviderContext($productExport, $salesChannelContext),
        ));

        return $renderContext;
    }

    /**
     * Return provider-specific render context fields. The base class adds common fields (name, referralCode,
     * affiliateCode, campaignCode) automatically.
     *
     * @return array<string, mixed>
     */
    abstract protected function buildProviderContext(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
    ): array;

    /**
     * @param array<string, mixed> $agenticConfig
     *
     * @return array{affiliateCode: ?string, campaignCode: ?string}
     */
    protected function resolveTrackingCodes(ProductExportEntity $productExport, array $agenticConfig): array
    {
        if ($agenticConfig['inheritStorefrontTrackingCodes'] ?? false) {
            $storefrontConfig = $productExport->getStorefrontSalesChannel()?->getConfiguration() ?? [];

            return [
                'affiliateCode' => $storefrontConfig['affiliateCode'] ?? null,
                'campaignCode' => $storefrontConfig['campaignCode'] ?? null,
            ];
        }

        return [
            'affiliateCode' => $agenticConfig['affiliateCode'] ?? null,
            'campaignCode' => $agenticConfig['campaignCode'] ?? null,
        ];
    }
}
