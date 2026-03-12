<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
class OpenAiProductExportProvider extends AbstractProductExportProvider
{
    public function __construct(
        private readonly ProductExportTemplateFileLoader $templateFileLoader
    ) {
    }

    public function supportsSalesChannelType(string $salesChannelTypeId): bool
    {
        return $salesChannelTypeId === Defaults::SALES_CHANNEL_TYPE_AGENTIC_AI;
    }

    public function getTechnicalName(): string
    {
        return 'open-ai';
    }

    public function getDefaultTemplate(ProductExportProvisioningContext $context): ProductExportTemplate
    {
        return new ProductExportTemplate(
            'openai-products-' . substr($context->salesChannel->getId(), 0, 8) . '.jsonl',
            ProductExportEntity::ENCODING_UTF8,
            ProductExportEntity::FILE_FORMAT_JSONL,
            86400,
            false,
            false,
            '',
            $this->templateFileLoader->load('open_ai/body.json.twig'),
            '',
        );
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
            'storeCountry' => $countryIso,
            'targetCountries' => [$countryIso],
            'sellerName' => $sellerName,
            'sellerUrl' => $sellerUrl,
        ]);

        return $renderContext;
    }
}
