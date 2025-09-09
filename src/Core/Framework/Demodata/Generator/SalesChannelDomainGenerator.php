<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

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
        private readonly EntityWriterInterface $writer,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly SalesChannelDomainDefinition $salesChannelDomainDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return SalesChannelDomainDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $storefrontSalesChannelId = $this->getStorefrontSalesChannelId($context);

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

        // Get the language that is not already the system language.
        $nonSystemLanguage = $this->getNonSystemLanguage($context);

        $isDE = $nonSystemLanguage->getName() === 'Deutsch';
        $domainPath = $isDE ? '/de' : '/en';
        $snippetSetName = $isDE ? 'BASE de-DE' : 'BASE en-GB';
        $appUrl = (string) (EnvironmentHelper::getVariable('APP_URL') ?? 'http://localhost:8000');
        $domainUrl = rtrim($appUrl, '/') . $domainPath;

        $newSalesChannelDomain = [
            'id' => Uuid::randomHex(),
            'url' => $domainUrl,
            'salesChannelId' => $storefrontSalesChannelId,
            'languageId' => $nonSystemLanguage->getId(),
            'snippetSetId' => $this->getSnippetSetId($context, $snippetSetName),
            'currencyId' => Defaults::CURRENCY,
        ];

        $writeContext = WriteContext::createFromContext($context->getContext());
        $this->writer->upsert(
            $this->salesChannelDomainDefinition,
            [$newSalesChannelDomain],
            $writeContext
        );

        $context->getConsole()->progressFinish();
    }

    private function getStorefrontSalesChannelId(DemodataContext $context): ?string
    {
        $salesChannelRepository = $this->registry->getRepository('sales_channel');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $salesChannelRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function getNonSystemLanguage(DemodataContext $context): ?LanguageEntity
    {
        $languageRepository = $this->registry->getRepository('language');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new NotEqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        return $languageRepository->search($criteria, $context->getContext())->first();
    }

    private function getSnippetSetId(DemodataContext $context, string $snippetSetName): ?string
    {
        $snippetSetRepository = $this->registry->getRepository('snippet_set');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $snippetSetName));

        return $snippetSetRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    /**
     * @param DemodataContext $context
     * @param string $storefrontSalesChannelId
     * @return EntitySearchResult<SalesChannelCollection>
     */
    private function getCurrentSalesChannelDomains(DemodataContext $context, string $storefrontSalesChannelId): EntitySearchResult
    {
        $salesChannelDomainRepository = $this->registry->getRepository('sales_channel_domain');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $storefrontSalesChannelId));

        return $salesChannelDomainRepository->search($criteria, $context->getContext());
    }
}
