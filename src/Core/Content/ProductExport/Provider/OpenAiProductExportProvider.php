<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 *
 * @internal
 */
#[Package('discovery')]
class OpenAiProductExportProvider extends AbstractProductExportProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    public function getTechnicalName(): string
    {
        return 'open-ai';
    }

    public function extendRenderContext(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $renderContext
    ): array {
        $storeCountry = $salesChannelContext->getShippingLocation()->getCountry()->getIso();
        $targetCountries = $this->resolveTargetCountries($salesChannelContext);
        $sellerUrl = $productExport->getSalesChannelDomain()?->getUrl();
        $sellerName = $salesChannelContext->getSalesChannel()->getName();

        $renderContext['provider'] = new ArrayStruct([
            'name' => $this->getTechnicalName(),
            'storeCountry' => $storeCountry,
            'targetCountries' => $targetCountries,
            'sellerName' => $sellerName,
            'sellerUrl' => $sellerUrl,
            'returnPolicyUrl' => $sellerUrl, // todo: update it with actual return policy url
            'isEligibleSearch' => true,
            'isEligibleCheckout' => false,
        ]);

        return $renderContext;
    }

    /**
     * @return list<string>|null
     */
    private function resolveTargetCountries(SalesChannelContext $salesChannelContext): ?array
    {
        $countries = $salesChannelContext->getSalesChannel()->getCountries();
        $targetCountries = $this->extractCountryIsoCodes($countries);

        if ($targetCountries !== []) {
            return $targetCountries;
        }

        $criteria = (new Criteria([$salesChannelContext->getSalesChannelId()]))
            ->addAssociation('countries');

        $salesChannel = $this->salesChannelRepository->search($criteria, $salesChannelContext->getContext())->first();

        if ($salesChannel === null) {
            return null;
        }

        $targetCountries = $this->extractCountryIsoCodes($salesChannel->getCountries());

        return $targetCountries !== [] ? $targetCountries : null;
    }

    /**
     * @param CountryCollection<CountryEntity>|null $countries
     *
     * @return list<string>
     */
    private function extractCountryIsoCodes(?iterable $countries): array
    {
        if ($countries === null) {
            return [];
        }

        $isoCodes = [];
        foreach ($countries as $country) {
            $iso = $country->getIso();

            if (!$iso) {
                continue;
            }

            $isoCodes[] = $iso;
        }

        return $isoCodes;
    }
}
