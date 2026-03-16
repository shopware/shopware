<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\Provider\ProductExportProviderRegistry;
use Shopware\Core\Content\ProductExport\Provider\ProductExportProvisioningContext;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
readonly class ProductExportProvisioner
{
    /**
     * @param EntityRepository<ProductExportCollection> $productExportRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     * @param EntityRepository<ProductStreamCollection> $productStreamRepository
     */
    public function __construct(
        private EntityRepository $productExportRepository,
        private EntityRepository $salesChannelRepository,
        private EntityRepository $salesChannelDomainRepository,
        private EntityRepository $productStreamRepository,
        private ProductExportProviderRegistry $providerRegistry
    ) {
    }

    public function provisionForSalesChannel(string $salesChannelId, Context $context): void
    {
        $salesChannel = $this->loadSalesChannel($salesChannelId, $context);

        if ($salesChannel === null || $salesChannel->getProductExports()?->count() > 0) {
            return;
        }

        $provider = $this->providerRegistry->getBySalesChannelType($salesChannel->getTypeId());

        if ($provider === null) {
            return;
        }

        $storefrontSalesChannel = $this->getDefaultStorefrontSalesChannel($context);
        $storefrontDomain = $storefrontSalesChannel ? $this->getDefaultStorefrontDomain($storefrontSalesChannel->getId(), $context) : null;
        $productStream = $this->getDefaultProductStream($context);

        if ($storefrontSalesChannel === null || $storefrontDomain === null || $productStream === null) {
            return;
        }

        $template = $provider->getDefaultTemplate(
            new ProductExportProvisioningContext($salesChannel, $storefrontSalesChannel, $storefrontDomain, $productStream)
        );

        $this->productExportRepository->create([
            [
                'id' => Uuid::randomHex(),
                'productStreamId' => $productStream->getId(),
                'storefrontSalesChannelId' => $storefrontSalesChannel->getId(),
                'salesChannelId' => $salesChannel->getId(),
                'salesChannelDomainId' => $storefrontDomain->getId(),
                'currencyId' => $storefrontDomain->getCurrencyId() ?? $storefrontSalesChannel->getCurrencyId(),
                'fileName' => $template->fileName,
                'accessKey' => Uuid::randomHex(),
                'encoding' => $template->encoding,
                'fileFormat' => $template->fileFormat,
                'includeVariants' => $template->includeVariants,
                'generateByCronjob' => $template->generateByCronjob,
                'interval' => $template->interval,
                'headerTemplate' => $template->headerTemplate,
                'bodyTemplate' => $template->bodyTemplate,
                'footerTemplate' => $template->footerTemplate,
            ],
        ], $context);
    }

    private function loadSalesChannel(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('productExports');

        return $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();
    }

    private function getDefaultStorefrontSalesChannel(Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();
    }

    private function getDefaultStorefrontDomain(string $salesChannelId, Context $context): ?SalesChannelDomainEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        return $this->salesChannelDomainRepository->search($criteria, $context)->getEntities()->first();
    }

    private function getDefaultProductStream(Context $context): ?ProductStreamEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('internal', false));

        return $this->productStreamRepository->search($criteria, $context)->getEntities()->first();
    }
}
