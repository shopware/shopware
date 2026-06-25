<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads the merged document configuration (global + sales-channel override)
 * for a document type. Reads typed columns directly; falls back to the JSON
 * `config` blob only for fields not yet migrated to columns.
 * Sales-channel non-null values override global.
 *
 * @internal
 */
#[Package('after-sales')]
final class DocumentConfigLoader implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, array<string, DocumentConfigBundle>>
     */
    private array $bundles = [];

    /**
     * @internal
     *
     * @param EntityRepository<DocumentBaseConfigCollection> $documentConfigRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly EntityRepository $documentConfigRepository,
        private readonly EntityRepository $countryRepository,
    ) {
    }

    /**
     * @internal
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'document_base_config.written' => 'reset',
        ];
    }

    public function reset(): void
    {
        $this->bundles = [];
    }

    public function load(string $documentType, string $salesChannelId, Context $context): DocumentConfigBundle
    {
        $cached = $this->bundles[$documentType][$salesChannelId] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('documentType.technicalName', $documentType))
            ->addAssociation('logo');

        $criteria->getAssociation('salesChannels')
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $rows = $this->documentConfigRepository->search($criteria, $context)->getEntities();

        $globalRow = $rows->filterByProperty('global', true)->first();
        $salesChannelRow = $rows
            ->filter(static fn (DocumentBaseConfigEntity $row): bool => ((int) $row->getSalesChannels()?->count()) > 0)
            ->first();

        $legacyConfig = $this->mergeJsonConfig($globalRow, $salesChannelRow);
        $documentConfig = $this->buildDocumentConfig($globalRow, $salesChannelRow, $documentType);
        $companyInfo = $this->buildCompanyInfo($legacyConfig, $context, $documentType);

        $bundle = new DocumentConfigBundle(
            config: $documentConfig,
            company: $companyInfo,
            legacyConfig: $legacyConfig,
        );

        $this->bundles[$documentType] ??= [];

        return $this->bundles[$documentType][$salesChannelId] = $bundle;
    }

    private function buildDocumentConfig(
        ?DocumentBaseConfigEntity $globalRow,
        ?DocumentBaseConfigEntity $salesChannelRow,
        string $documentType,
    ): DocumentConfig {
        $pageSize = $salesChannelRow?->getPageSize() ?? $globalRow?->getPageSize() ?? '';
        $pageOrientation = $salesChannelRow?->getPageOrientation() ?? $globalRow?->getPageOrientation() ?? '';
        $itemsPerPage = $salesChannelRow?->getItemsPerPage() ?? $globalRow?->getItemsPerPage() ?? 0;

        $this->ensureRequiredValues(DocumentConfig::class, $documentType, [
            'pageSize' => $pageSize,
            'pageOrientation' => $pageOrientation,
            'itemsPerPage' => $itemsPerPage > 0 ? $itemsPerPage : null,
        ]);

        return new DocumentConfig(
            pageSize: $pageSize,
            pageOrientation: $pageOrientation,
            itemsPerPage: $itemsPerPage,
            filenamePrefix: $salesChannelRow?->getFilenamePrefix() ?? $globalRow?->getFilenamePrefix(),
            filenameSuffix: $salesChannelRow?->getFilenameSuffix() ?? $globalRow?->getFilenameSuffix(),
            logo: $salesChannelRow?->getLogo() ?? $globalRow?->getLogo(),
            displayHeader: $salesChannelRow?->getDisplayHeader() ?? $globalRow?->getDisplayHeader() ?? false,
            displayFooter: $salesChannelRow?->getDisplayFooter() ?? $globalRow?->getDisplayFooter() ?? false,
            displayPageCount: $salesChannelRow?->getDisplayPageCount() ?? $globalRow?->getDisplayPageCount() ?? false,
            displayCompanyAddress: $salesChannelRow?->getDisplayCompanyAddress() ?? $globalRow?->getDisplayCompanyAddress() ?? false,
            displayReturnAddress: $salesChannelRow?->getDisplayReturnAddress() ?? $globalRow?->getDisplayReturnAddress() ?? false,
            displayCustomerVatId: $salesChannelRow?->getDisplayCustomerVatId() ?? $globalRow?->getDisplayCustomerVatId() ?? false,
        );
    }

    /**
     * @param array<string, mixed> $legacyConfig
     */
    private function buildCompanyInfo(array $legacyConfig, Context $context, string $documentType): CompanyInfo
    {
        $companyCountryId = $legacyConfig['companyCountryId'] ?? null;
        $companyCountry = null;

        if (\is_string($companyCountryId) && Uuid::isValid($companyCountryId)) {
            $companyCountry = $this->countryRepository->search(new Criteria([$companyCountryId]), $context)->first();
        }

        if (!$companyCountry instanceof CountryEntity) {
            throw DocumentV2Exception::legacyConfigMissingRequiredFields(
                CompanyInfo::class,
                $documentType,
                'companyCountry'
            );
        }

        $required = [
            'companyName' => $legacyConfig['companyName'] ?? null,
            'companyStreet' => $legacyConfig['companyStreet'] ?? null,
            'companyZipcode' => $legacyConfig['companyZipcode'] ?? null,
            'companyCity' => $legacyConfig['companyCity'] ?? null,
        ];

        $this->ensureRequiredValues(CompanyInfo::class, $documentType, $required);

        return new CompanyInfo(
            companyName: (string) $required['companyName'],
            companyStreet: (string) $required['companyStreet'],
            companyZipcode: (string) $required['companyZipcode'],
            companyCity: (string) $required['companyCity'],
            companyCountry: $companyCountry,
            companyEmail: $legacyConfig['companyEmail'] ?? null,
            companyPhone: $legacyConfig['companyPhone'] ?? null,
            companyUrl: $legacyConfig['companyUrl'] ?? null,
            executiveDirector: $legacyConfig['executiveDirector'] ?? null,
            taxNumber: $legacyConfig['taxNumber'] ?? null,
            taxOffice: $legacyConfig['taxOffice'] ?? null,
            vatId: $legacyConfig['vatId'] ?? null,
            bankName: $legacyConfig['bankName'] ?? null,
            bankIban: $legacyConfig['bankIban'] ?? null,
            bankBic: $legacyConfig['bankBic'] ?? null,
            placeOfJurisdiction: $legacyConfig['placeOfJurisdiction'] ?? null,
            placeOfFulfillment: $legacyConfig['placeOfFulfillment'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeJsonConfig(?DocumentBaseConfigEntity $globalRow, ?DocumentBaseConfigEntity $salesChannelRow): array
    {
        $merged = $globalRow?->getConfig() ?? [];

        foreach ($salesChannelRow?->getConfig() ?? [] as $key => $value) {
            if ($value !== null) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function ensureRequiredValues(string $target, string $documentType, array $values): void
    {
        foreach ($values as $field => $value) {
            if ($value !== null && $value !== '') {
                continue;
            }

            throw DocumentV2Exception::legacyConfigMissingRequiredFields(
                $target,
                $documentType,
                $field
            );
        }
    }
}
