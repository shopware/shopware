<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 *
 * @internal
 */
#[Package('discovery')]
class OpenAiProductExportProvider extends AbstractProductExportProvider
{
    public function getTechnicalName(): string
    {
        return 'open-ai';
    }

    public function extendRenderContext(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $renderContext
    ): array {
        $countryIso = $salesChannelContext->getShippingLocation()->getCountry()->getIso() ?? '';
        $sellerUrl = $productExport->getSalesChannelDomain()?->getUrl() ?? '';
        $sellerName = $salesChannelContext->getSalesChannel()->getName() ?? '';

        $renderContext['provider'] = new ArrayStruct([
            'name' => $this->getTechnicalName(),
            'storeCountry' => $countryIso, // todo: update it with actual store country
            'targetCountries' => [$countryIso], // todo: update it with actual target countries
            'sellerName' => $sellerName,
            'sellerUrl' => $sellerUrl,
            'returnPolicyUrl' => $sellerUrl, // todo: update it with actual return policy url
            'isEligibleSearch' => true,
            'isEligibleCheckout' => false,
        ]);

        return $renderContext;
    }
}
