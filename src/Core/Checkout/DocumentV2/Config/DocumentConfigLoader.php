<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
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
     * @var array<string, array<string, array<string, DocumentConfigBundle>>>
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
        $versionId = $context->getVersionId();
        $cached = $this->bundles[$documentType][$versionId][$salesChannelId] ?? null;

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
        $salesChannelRow = $rows->filterByProperty('global', false)->first();

        $legacyConfig = $this->mergeJsonConfig($globalRow, $salesChannelRow);
        $documentConfig = $this->buildDocumentConfig($globalRow, $salesChannelRow, $documentType);
        $companyInfo = $this->buildCompanyInfo($legacyConfig, $context, $documentType);

        $bundle = new DocumentConfigBundle(
            config: $documentConfig,
            company: $companyInfo,
            legacyConfig: $legacyConfig,
        );

        $this->bundles[$documentType][$versionId] ??= [];

        return $this->bundles[$documentType][$versionId][$salesChannelId] = $bundle;
    }

    private function buildDocumentConfig(
        ?DocumentBaseConfigEntity $globalRow,
        ?DocumentBaseConfigEntity $salesChannelRow,
        string $documentType,
    ): DocumentConfig {
        $pageSize = $salesChannelRow?->getPageSize() ?? $globalRow?->getPageSize() ?? '';
        $pageOrientation = $salesChannelRow?->getPageOrientation() ?? $globalRow?->getPageOrientation() ?? '';
        $itemsPerPage = $salesChannelRow?->getItemsPerPage() ?? $globalRow?->getItemsPerPage();

        $this->ensureRequiredValues(DocumentConfig::class, $documentType, [
            'pageSize' => $pageSize,
            'pageOrientation' => $pageOrientation,
            'itemsPerPage' => $itemsPerPage,
        ]);

        return new DocumentConfig(
            pageSize: $pageSize,
            pageOrientation: $pageOrientation,
            itemsPerPage: (int) $itemsPerPage,
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
            companyEmail: $this->stringOrNull($legacyConfig['companyEmail'] ?? null),
            companyPhone: $this->stringOrNull($legacyConfig['companyPhone'] ?? null),
            companyUrl: $this->stringOrNull($legacyConfig['companyUrl'] ?? null),
            executiveDirector: $this->stringOrNull($legacyConfig['executiveDirector'] ?? null),
            taxNumber: $this->stringOrNull($legacyConfig['taxNumber'] ?? null),
            taxOffice: $this->stringOrNull($legacyConfig['taxOffice'] ?? null),
            vatId: $this->stringOrNull($legacyConfig['vatId'] ?? null),
            bankName: $this->stringOrNull($legacyConfig['bankName'] ?? null),
            bankIban: $this->stringOrNull($legacyConfig['bankIban'] ?? null),
            bankBic: $this->stringOrNull($legacyConfig['bankBic'] ?? null),
            placeOfJurisdiction: $this->stringOrNull($legacyConfig['placeOfJurisdiction'] ?? null),
            placeOfFulfillment: $this->stringOrNull($legacyConfig['placeOfFulfillment'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeJsonConfig(?DocumentBaseConfigEntity $globalRow, ?DocumentBaseConfigEntity $salesChannelRow): array
    {
        // The `config` JSON column is deprecated in v6.7 and `getConfig()` triggers a deprecation
        // notice. During the v6.7 → v6.8 transition the loader still needs the JSON for fields
        // (company info, plugin extensions) not yet promoted to typed columns, so silence here.
        return Feature::silent('v6.8.0.0', static function () use ($globalRow, $salesChannelRow): array {
            $merged = $globalRow?->getConfig() ?? [];

            foreach ($salesChannelRow?->getConfig() ?? [] as $key => $value) {
                if ($value !== null) {
                    $merged[$key] = $value;
                }
            }

            return $merged;
        });
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

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
