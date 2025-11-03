<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelDomainGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function getDefinition(): string
    {
        return SalesChannelDomainDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $storefrontSalesChannelId = $this->getStorefrontSalesChannel($context);

        // Get the languages that are not the system language.
        $languages = $this->getNonSystemLanguages($context);

        if ($languages->count() === 0) {
            $context->getConsole()->note('Skipping sales_channel_domain generation. No other language found.');

            return;
        }

        if (!$storefrontSalesChannelId) {
            $context->getConsole()->note('Skipping sales_channel_domain generation. No storefront sales channel found.');

            return;
        }

        // If there is already more than one sales channel domain, do nothing.
        if ($this->getCurrentSalesChannelDomains($context, $storefrontSalesChannelId)->count() > 1) {
            $context->getConsole()->note('Skipping sales_channel_domain generation. Already exists.');

            return;
        }

        $context->getConsole()->progressStart($numberOfItems);

        $this->addLanguagesToSalesChannel($context, $storefrontSalesChannelId, $languages->getIds());

        $appUrl = (string) (EnvironmentHelper::getVariable('APP_URL') ?? 'http://localhost:8000');
        $salesChannelDomains = [];

        foreach ($languages as $language) {
            $locale = $language->getLocale();

            if ($locale === null) {
                continue;
            }

            $localeCode = $locale->getCode();
            $url = rtrim($appUrl, '/') . '/' . ltrim(strtolower($localeCode), '/');
            $snippetSetId = $this->getSnippetSetByIso($context, $localeCode);

            // If no matching snippet set is found, do not add the sales channel domain.
            if ($snippetSetId === null) {
                continue;
            }

            $salesChannelDomains[] = [
                'id' => Uuid::randomHex(),
                'url' => $url,
                'salesChannelId' => $storefrontSalesChannelId,
                'languageId' => $language->getId(),
                'snippetSetId' => $snippetSetId,
                'currencyId' => Defaults::CURRENCY,
            ];
        }

        $salesChannelDomainRepository = $this->registry->getRepository('sales_channel_domain');
        $salesChannelDomainRepository->upsert($salesChannelDomains, $context->getContext());

        $context->getConsole()->progressFinish();
    }

    /**
     * @param array<string> $languageIds
     */
    private function addLanguagesToSalesChannel(DemodataContext $context, string $salesChannelId, array $languageIds): void
    {
        $salesChannelRepository = $this->registry->getRepository('sales_channel');

        $salesChannelRepository->update([
            [
                'id' => $salesChannelId,
                'languages' => array_map(
                    fn (string $languageId) => ['id' => $languageId],
                    $languageIds
                ),
            ],
        ], $context->getContext());
    }

    private function getStorefrontSalesChannel(DemodataContext $context): ?string
    {
        $salesChannelRepository = $this->registry->getRepository('sales_channel');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $salesChannelRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function getNonSystemLanguages(DemodataContext $context): LanguageCollection
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = $this->registry->getRepository('language');
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('id', Defaults::LANGUAGE_SYSTEM));
        $criteria->addAssociation('locale');

        return $languageRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function getSnippetSetByIso(DemodataContext $context, string $iso): ?string
    {
        $snippetSetRepository = $this->registry->getRepository('snippet_set');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('iso', $iso));

        return $snippetSetRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function getCurrentSalesChannelDomains(DemodataContext $context, string $storefrontSalesChannelId): SalesChannelDomainCollection
    {
        /** @var EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository */
        $salesChannelDomainRepository = $this->registry->getRepository('sales_channel_domain');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $storefrontSalesChannelId));

        return $salesChannelDomainRepository->search($criteria, $context->getContext())->getEntities();
    }
}
