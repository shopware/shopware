<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
    private const COMPANY_INFO_CONFIG_DOMAIN = 'core.basicInformation';

    /**
     * Map of config keys to internal company-info keys
     *
     * @var array<string, string>
     */
    private const COMPANY_INFO_CONFIG_KEYS = [
        'companyLogoId' => 'logoId',
        'companyName' => 'companyName',
        'companyEmail' => 'companyEmail',
        'companyPhone' => 'companyPhone',
        'companyStreet' => 'companyStreet',
        'companyCountryId' => 'companyCountryId',
        'companyZipcode' => 'companyZipcode',
        'companyCity' => 'companyCity',
        'companyUrl' => 'companyUrl',
        'companyTaxNumber' => 'taxNumber',
        'companyTaxOffice' => 'taxOffice',
        'companyVatId' => 'vatId',
        'companyBankName' => 'bankName',
        'companyBankIban' => 'bankIban',
        'companyBankBic' => 'bankBic',
        'companyPlaceOfJurisdiction' => 'placeOfJurisdiction',
        'companyPlaceOfFulfillment' => 'placeOfFulfillment',
        'companyExecutiveDirector' => 'executiveDirector',
    ];

    /**
     * @var array<string, array<string, DocumentConfigBundle>>
     */
    private array $bundles = [];

    /**
     * @internal
     *
     * @param EntityRepository<DocumentBaseConfigCollection> $documentConfigRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $documentConfigRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
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
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function reset(): void
    {
        $this->bundles = [];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if (!str_starts_with($event->getKey(), self::COMPANY_INFO_CONFIG_DOMAIN . '.')) {
            return;
        }

        $this->reset();
    }

    public function load(string $documentType, string $salesChannelId, Context $context): DocumentConfigBundle
    {
        $cached = $this->bundles[$documentType][$salesChannelId] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('typeName', $documentType))
            ->addAssociation('logo');

        $criteria->getAssociation('salesChannels')
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $rows = $this->documentConfigRepository->search($criteria, $context)->getEntities();

        $globalRow = $rows->filterByProperty('global', true)->first();
        $salesChannelRow = $rows
            ->filter(static fn (DocumentBaseConfigEntity $row): bool => ((int) $row->getSalesChannels()?->count()) > 0)
            ->first();

        $legacyConfig = $this->mergeJsonConfig($globalRow, $salesChannelRow);
        $systemConfigCompanyInfo = $this->resolveCompanyInfoFromSystemConfig($salesChannelId);
        $effectiveCompanyInfo = $systemConfigCompanyInfo ?? $legacyConfig;
        $appConfig = $this->documentTypeRegistry->getAppConfig($documentType);

        $documentConfig = $this->buildDocumentConfig($globalRow, $salesChannelRow, $systemConfigCompanyInfo, $documentType, $context, $appConfig);
        $companyInfo = $this->buildDocumentCompanyInfo($effectiveCompanyInfo, $context, $documentType);
        $displayOptions = $this->buildDisplayOptions($globalRow, $salesChannelRow, $legacyConfig, $appConfig);

        $bundle = new DocumentConfigBundle(
            config: $documentConfig,
            company: $companyInfo,
            display: $displayOptions,
            legacyConfig: $legacyConfig,
        );

        $this->bundles[$documentType] ??= [];

        return $this->bundles[$documentType][$salesChannelId] = $bundle;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCompanyInfoFromSystemConfig(string $salesChannelId): ?array
    {
        $basicInformationConfig = $this->systemConfigService->getDomain(self::COMPANY_INFO_CONFIG_DOMAIN, $salesChannelId, true);
        $companyInfoConfig = [];

        foreach (self::COMPANY_INFO_CONFIG_KEYS as $configKey => $targetKey) {
            $value = $basicInformationConfig[self::COMPANY_INFO_CONFIG_DOMAIN . '.' . $configKey] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $companyInfoConfig[$targetKey] = $value;
        }

        if ($companyInfoConfig !== []) {
            return $companyInfoConfig;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $systemConfigCompanyInfo
     * @param array<string, scalar> $appConfig
     */
    private function buildDocumentConfig(
        ?DocumentBaseConfigEntity $globalRow,
        ?DocumentBaseConfigEntity $salesChannelRow,
        ?array $systemConfigCompanyInfo,
        string $documentType,
        Context $context,
        array $appConfig,
    ): DocumentConfig {
        if ($globalRow === null && $salesChannelRow === null) {
            if (DocumentType::tryFrom($documentType) !== null) {
                throw DocumentV2Exception::invalidDocumentType($documentType);
            }

            return new DocumentConfig(
                pageSize: (string) ($appConfig['pageSize'] ?? 'a4'),
                pageOrientation: (string) ($appConfig['pageOrientation'] ?? 'portrait'),
                itemsPerPage: (int) ($appConfig['itemsPerPage'] ?? 10),
                filenamePrefix: isset($appConfig['filenamePrefix']) ? (string) $appConfig['filenamePrefix'] : null,
                filenameSuffix: isset($appConfig['filenameSuffix']) ? (string) $appConfig['filenameSuffix'] : null,
                logo: $this->resolveLogo(null, null, $systemConfigCompanyInfo, $context),
            );
        }

        $pageSize = $salesChannelRow?->getPageSize() ?? $globalRow?->getPageSize() ?? '';
        $pageOrientation = $salesChannelRow?->getPageOrientation() ?? $globalRow?->getPageOrientation() ?? '';
        $itemsPerPage = $salesChannelRow?->getItemsPerPage() ?? $globalRow?->getItemsPerPage() ?? 0;
        $logo = $this->resolveLogo($globalRow, $salesChannelRow, $systemConfigCompanyInfo, $context);

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
            filenameInfixes: array_merge(
                $globalRow?->getFilenameInfixes() ?? [],
                $salesChannelRow?->getFilenameInfixes() ?? [],
            ),
            logo: $logo,
        );
    }

    /**
     * @param array<string, mixed>|null $systemConfigCompanyInfo
     */
    private function resolveLogo(
        ?DocumentBaseConfigEntity $globalRow,
        ?DocumentBaseConfigEntity $salesChannelRow,
        ?array $systemConfigCompanyInfo,
        Context $context,
    ): ?MediaEntity {
        if ($systemConfigCompanyInfo === null) {
            return $salesChannelRow?->getLogo() ?? $globalRow?->getLogo();
        }

        $logoId = $systemConfigCompanyInfo['logoId'] ?? null;

        if (!\is_string($logoId) || !Uuid::isValid($logoId)) {
            return null;
        }

        $logo = $this->mediaRepository->search(new Criteria([$logoId]), $context)->getEntities()->first();

        return $logo instanceof MediaEntity ? $logo : null;
    }

    /**
     * @param array<string, mixed> $companyInfoConfig
     */
    private function buildDocumentCompanyInfo(array $companyInfoConfig, Context $context, string $documentType): DocumentCompanyInfo
    {
        $companyCountryId = $companyInfoConfig['companyCountryId'] ?? null;
        $companyCountry = null;

        if (\is_string($companyCountryId) && Uuid::isValid($companyCountryId)) {
            $companyCountry = $this->countryRepository->search(new Criteria([$companyCountryId]), $context)->getEntities()->first();
        }

        if (!$companyCountry instanceof CountryEntity) {
            throw DocumentV2Exception::configMissingRequiredFields(
                DocumentCompanyInfo::class,
                $documentType,
                'companyCountry'
            );
        }

        $required = [
            'companyName' => $companyInfoConfig['companyName'] ?? null,
            'companyStreet' => $companyInfoConfig['companyStreet'] ?? null,
            'companyZipcode' => $companyInfoConfig['companyZipcode'] ?? null,
            'companyCity' => $companyInfoConfig['companyCity'] ?? null,
        ];

        $this->ensureRequiredValues(DocumentCompanyInfo::class, $documentType, $required);

        return new DocumentCompanyInfo(
            companyName: (string) $required['companyName'],
            companyStreet: (string) $required['companyStreet'],
            companyZipcode: (string) $required['companyZipcode'],
            companyCity: (string) $required['companyCity'],
            companyCountry: $companyCountry,
            companyEmail: $companyInfoConfig['companyEmail'] ?? null,
            companyPhone: $companyInfoConfig['companyPhone'] ?? null,
            companyUrl: $companyInfoConfig['companyUrl'] ?? null,
            executiveDirector: $companyInfoConfig['executiveDirector'] ?? null,
            taxNumber: $companyInfoConfig['taxNumber'] ?? null,
            taxOffice: $companyInfoConfig['taxOffice'] ?? null,
            vatId: $companyInfoConfig['vatId'] ?? null,
            bankName: $companyInfoConfig['bankName'] ?? null,
            bankIban: $companyInfoConfig['bankIban'] ?? null,
            bankBic: $companyInfoConfig['bankBic'] ?? null,
            placeOfJurisdiction: $companyInfoConfig['placeOfJurisdiction'] ?? null,
            placeOfFulfillment: $companyInfoConfig['placeOfFulfillment'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $legacyConfig
     * @param array<string, scalar> $appConfig
     */
    private function buildDisplayOptions(
        ?DocumentBaseConfigEntity $globalRow,
        ?DocumentBaseConfigEntity $salesChannelRow,
        array $legacyConfig,
        array $appConfig,
    ): DocumentDisplayOptions {
        return new DocumentDisplayOptions(
            displayHeader: $this->resolveFlag($appConfig, 'displayHeader', $salesChannelRow?->getDisplayHeader() ?? $globalRow?->getDisplayHeader()),
            displayFooter: $this->resolveFlag($appConfig, 'displayFooter', $salesChannelRow?->getDisplayFooter() ?? $globalRow?->getDisplayFooter()),
            displayPageCount: $this->resolveFlag($appConfig, 'displayPageCount', $salesChannelRow?->getDisplayPageCount() ?? $globalRow?->getDisplayPageCount()),
            displayCompanyAddress: $this->resolveFlag($appConfig, 'displayCompanyAddress', $salesChannelRow?->getDisplayCompanyAddress() ?? $globalRow?->getDisplayCompanyAddress()),
            displayReturnAddress: $this->resolveFlag($appConfig, 'displayReturnAddress', $salesChannelRow?->getDisplayReturnAddress() ?? $globalRow?->getDisplayReturnAddress()),
            displayCustomerVatId: $this->resolveFlag($appConfig, 'displayCustomerVatId', $salesChannelRow?->getDisplayCustomerVatId() ?? $globalRow?->getDisplayCustomerVatId()),
            displayLineItems: $this->resolveFlag($appConfig, 'displayLineItems', $legacyConfig['displayLineItems'] ?? null),
            displayLineItemPosition: $this->resolveFlag($appConfig, 'displayLineItemPosition', $legacyConfig['displayLineItemPosition'] ?? null),
            displayPrices: $this->resolveFlag($appConfig, 'displayPrices', $legacyConfig['displayPrices'] ?? null),
            displayDivergentDeliveryAddress: $this->resolveFlag($appConfig, 'displayDivergentDeliveryAddress', $legacyConfig['displayDivergentDeliveryAddress'] ?? null),
            deliveryCountries: $legacyConfig['deliveryCountries'] ?? [],
        );
    }

    /**
     * @param array<string, scalar> $appConfig
     */
    private function resolveFlag(array $appConfig, string $key, mixed $merchantValue): bool
    {
        return (bool) ($appConfig[$key] ?? $merchantValue ?? false);
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

            throw DocumentV2Exception::configMissingRequiredFields(
                $target,
                $documentType,
                $field
            );
        }
    }
}
