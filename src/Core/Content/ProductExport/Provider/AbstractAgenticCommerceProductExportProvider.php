<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingListener;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Base class for Agentic Commerce product export providers.
 *
 * Handles common functionality like tracking.
 * Concrete providers only need to implement {@see buildProviderContext()} for their format-specific fields.
 *
 * @deprecated tag:v6.8.0 - Will be removed and is going to be part of SwagAgenticCommerce
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
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'Will be part of SwagAgenticCommerce'));

        $agenticConfig = $productExport->getSalesChannel()?->getConfiguration() ?? [];

        $renderContext['provider'] = new ArrayStruct(array_merge(
            [
                'name' => $this->getTechnicalName(),
                SalesChannelTrackingListener::QUERY_PARAM => $productExport->getSalesChannelId(),
                OrderService::AFFILIATE_CODE_KEY => $agenticConfig[OrderService::AFFILIATE_CODE_KEY] ?? null,
                OrderService::CAMPAIGN_CODE_KEY => $agenticConfig[OrderService::CAMPAIGN_CODE_KEY] ?? null,
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
}
