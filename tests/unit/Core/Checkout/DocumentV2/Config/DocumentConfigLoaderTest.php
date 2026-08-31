<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\DocumentV2\App\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentConfigLoader::class)]
class DocumentConfigLoaderTest extends TestCase
{
    private const COMPANY_COUNTRY_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c0';
    private const COMPANY_INFO_CONFIG_DOMAIN = 'core.basicInformation';
    private const COMPANY_INFO_CONFIG_PREFIX = self::COMPANY_INFO_CONFIG_DOMAIN . '.';
    private const LEGACY_LOGO_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c1';
    private const COMPANY_INFO_LOGO_ID = '0190a3f5cafa70f5b6e7e5b8f0c0c0c2';

    private DocumentTypeRegistry $documentTypeRegistry;

    protected function setUp(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);

        $this->documentTypeRegistry = new DocumentTypeRegistry([], $storage);
    }

    public function testLoadPicksMatchingSalesChannelRowWhenMultipleNonGlobalRowsReturned(): void
    {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
        );

        $matchingRow = $this->createBaseConfig(
            global: false,
            pageSize: 'Letter',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
        );

        $otherRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A5',
            companyName: 'Wrong Channel GmbH',
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $otherRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame('Letter', $bundle->config->pageSize);
        static::assertSame('Matching Channel GmbH', $bundle->company->companyName);
    }

    public function testLoadSalesChannelFilenameInfixOverridesGlobalPerFormat(): void
    {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            filenameInfixes: ['zugferd_embedded_pdf' => 'global-infix'],
        );

        $matchingRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A4',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
            filenameInfixes: ['zugferd_embedded_pdf' => 'channel-infix'],
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame(['zugferd_embedded_pdf' => 'channel-infix'], $bundle->config->filenameInfixes);
    }

    public function testLoadKeepsGlobalFilenameInfixesWhenSalesChannelMapIsEmpty(): void
    {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            filenameInfixes: ['zugferd_embedded_pdf' => '_zugferd'],
        );

        $matchingRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A4',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
            filenameInfixes: [],
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame(['zugferd_embedded_pdf' => '_zugferd'], $bundle->config->filenameInfixes);
    }

    public function testLoadMergesFilenameInfixesPerFormat(): void
    {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            filenameInfixes: ['zugferd_embedded_pdf' => '_zugferd'],
        );

        $matchingRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A4',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
            filenameInfixes: ['pdf' => '_channel'],
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame(
            ['zugferd_embedded_pdf' => '_zugferd', 'pdf' => '_channel'],
            $bundle->config->filenameInfixes,
        );
    }

    public function testLoadKeepsSalesChannelFilenameInfixesWhenGlobalHasNone(): void
    {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            filenameInfixes: null,
        );

        $matchingRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A4',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
            filenameInfixes: ['pdf' => '_channel'],
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame(['pdf' => '_channel'], $bundle->config->filenameInfixes);
    }

    public static function filenamePrefixAndSuffixProvider(): \Generator
    {
        yield 'sales channel empty string falls back to global' => [
            'channelPrefix' => '',
            'channelSuffix' => '',
            'globalPrefix' => 'invoice_',
            'globalSuffix' => '_global',
            'expectedPrefix' => 'invoice_',
            'expectedSuffix' => '_global',
        ];

        yield 'sales channel value overrides global' => [
            'channelPrefix' => 'channel_',
            'channelSuffix' => '_channel',
            'globalPrefix' => 'invoice_',
            'globalSuffix' => '_global',
            'expectedPrefix' => 'channel_',
            'expectedSuffix' => '_channel',
        ];

        yield 'nothing configured stays null' => [
            'channelPrefix' => null,
            'channelSuffix' => null,
            'globalPrefix' => null,
            'globalSuffix' => null,
            'expectedPrefix' => null,
            'expectedSuffix' => null,
        ];

        yield 'global empty string counts as unconfigured' => [
            'channelPrefix' => null,
            'channelSuffix' => null,
            'globalPrefix' => '',
            'globalSuffix' => '',
            'expectedPrefix' => null,
            'expectedSuffix' => null,
        ];

        yield 'empty strings on both sides stay null' => [
            'channelPrefix' => '',
            'channelSuffix' => '',
            'globalPrefix' => '',
            'globalSuffix' => null,
            'expectedPrefix' => null,
            'expectedSuffix' => null,
        ];
    }

    #[DataProvider('filenamePrefixAndSuffixProvider')]
    public function testLoadResolvesFilenamePrefixAndSuffix(
        ?string $channelPrefix,
        ?string $channelSuffix,
        ?string $globalPrefix,
        ?string $globalSuffix,
        ?string $expectedPrefix,
        ?string $expectedSuffix,
    ): void {
        $matchingSalesChannelId = Uuid::randomHex();

        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            filenamePrefix: $globalPrefix,
            filenameSuffix: $globalSuffix,
        );

        $matchingRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A4',
            companyName: 'Matching Channel GmbH',
            salesChannelId: $matchingSalesChannelId,
            filenamePrefix: $channelPrefix,
            filenameSuffix: $channelSuffix,
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $matchingRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $matchingSalesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame($expectedPrefix, $bundle->config->filenamePrefix);
        static::assertSame($expectedSuffix, $bundle->config->filenameSuffix);
    }

    public function testLoadFallsBackToGlobalWhenNoSalesChannelRowMatches(): void
    {
        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
        );

        $unrelatedRow = $this->createBaseConfig(
            global: false,
            pageSize: 'A5',
            companyName: 'Unrelated GmbH',
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow, $unrelatedRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame('A4', $bundle->config->pageSize);
        static::assertSame('Global GmbH', $bundle->company->companyName);
        static::assertSame([], $bundle->config->filenameInfixes);
    }

    public function testLoadRejectsZeroItemsPerPage(): void
    {
        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Global GmbH',
            itemsPerPage: 0,
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $this->expectException(DocumentV2Exception::class);
        $this->expectExceptionMessageMatches('/itemsPerPage/');

        $loader->load(
            DocumentType::INVOICE->value,
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );
    }

    public function testLoadPrefersCompanyInfoFromSystemConfigWhenPresent(): void
    {
        $salesChannelId = Uuid::randomHex();
        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Legacy GmbH',
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService([
                'companyName' => 'System Config GmbH',
                'companyStreet' => 'System Street 5',
                'companyZipcode' => '54321',
                'companyCity' => 'System City',
                'companyCountryId' => self::COMPANY_COUNTRY_ID,
                'companyLogoId' => self::COMPANY_INFO_LOGO_ID,
            ], $salesChannelId),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $salesChannelId,
            Context::createDefaultContext(),
        );

        static::assertSame('System Config GmbH', $bundle->company->companyName);
        static::assertSame('System Street 5', $bundle->company->companyStreet);
        static::assertSame(self::COMPANY_INFO_LOGO_ID, $bundle->config->logo?->getId());
    }

    public function testLoadRejectsInvalidCompanyInfoFromSystemConfigWithoutLegacyFallback(): void
    {
        $salesChannelId = Uuid::randomHex();
        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Legacy GmbH',
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService([
                'companyName' => 'System Config GmbH',
            ], $salesChannelId),
            $this->documentTypeRegistry,
        );

        $this->expectException(DocumentV2Exception::class);
        $this->expectExceptionMessageMatches('/companyCountry|companyStreet|companyZipcode|companyCity/');

        $loader->load(
            DocumentType::INVOICE->value,
            $salesChannelId,
            Context::createDefaultContext(),
        );
    }

    public function testLoadDoesNotFallBackToLegacyLogoWhenCompanyInfoConfigHasNoLogoId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Legacy GmbH',
            logoId: self::LEGACY_LOGO_ID,
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService([
                'companyName' => 'System Config GmbH',
                'companyStreet' => 'System Street 5',
                'companyZipcode' => '54321',
                'companyCity' => 'System City',
                'companyCountryId' => self::COMPANY_COUNTRY_ID,
            ], $salesChannelId),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            $salesChannelId,
            Context::createDefaultContext(),
        );

        static::assertNull($bundle->config->logo);
    }

    public function testLoadFallsBackToLegacyLogoWhenCompanyInfoConfigIsAbsent(): void
    {
        $globalRow = $this->createBaseConfig(
            global: true,
            pageSize: 'A4',
            companyName: 'Legacy GmbH',
            logoId: self::LEGACY_LOGO_ID,
        );

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([$globalRow])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $bundle = $loader->load(
            DocumentType::INVOICE->value,
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame(self::LEGACY_LOGO_ID, $bundle->config->logo?->getId());
    }

    public function testLoadThrowsForUnknownDocumentTypeWithoutConfigRows(): void
    {
        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService(),
            $this->documentTypeRegistry,
        );

        $this->expectExceptionObject(DocumentV2Exception::invalidDocumentType('unknown_document_type'));

        $loader->load('unknown_document_type', Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testLoadReturnsManifestDefaultsForRegisteredAppTypeWithoutConfigRows(): void
    {
        $salesChannelId = Uuid::randomHex();

        $documentRepo = new StaticEntityRepository(
            [new DocumentBaseConfigCollection([])],
            new DocumentBaseConfigDefinition(),
        );

        $countryRepo = new StaticEntityRepository(
            [new CountryCollection([$this->createCountry()])],
            new CountryDefinition(),
        );

        $loader = new DocumentConfigLoader(
            $documentRepo,
            $countryRepo,
            $this->createMediaRepository(),
            $this->createSystemConfigService([
                'companyName' => 'System Config GmbH',
                'companyStreet' => 'System Street 5',
                'companyZipcode' => '54321',
                'companyCity' => 'System City',
                'companyCountryId' => self::COMPANY_COUNTRY_ID,
            ], $salesChannelId),
            $this->documentTypeRegistryWithAppType('swag_warranty', [
                'pageSize' => 'a5',
                'pageOrientation' => 'landscape',
                'itemsPerPage' => 5,
                'filenamePrefix' => 'warranty',
            ]),
        );

        $bundle = $loader->load('swag_warranty', $salesChannelId, Context::createDefaultContext());

        static::assertSame('a5', $bundle->config->pageSize);
        static::assertSame('landscape', $bundle->config->pageOrientation);
        static::assertSame(5, $bundle->config->itemsPerPage);
        static::assertSame('warranty', $bundle->config->filenamePrefix);
    }

    /**
     * @param array<string, scalar> $appConfig
     */
    private function documentTypeRegistryWithAppType(string $identifier, array $appConfig): DocumentTypeRegistry
    {
        $feature = new AppFeature(
            appId: 'app-id',
            appName: 'SwagWarranty',
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable(),
            config: new AppDocumentTypeConfig($identifier, ['html', 'pdf'], ['en-GB' => 'Warranty'], $appConfig),
        );

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([$feature]);

        return new DocumentTypeRegistry([], $storage);
    }

    /**
     * @param array<string, string>|null $filenameInfixes
     */
    private function createBaseConfig(
        bool $global,
        string $pageSize,
        string $companyName,
        ?string $salesChannelId = null,
        int $itemsPerPage = 10,
        ?string $logoId = null,
        ?array $filenameInfixes = null,
        ?string $filenamePrefix = null,
        ?string $filenameSuffix = null,
    ): DocumentBaseConfigEntity {
        $entity = new DocumentBaseConfigEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setId(Uuid::randomHex());
        $entity->setGlobal($global);
        $entity->setPageSize($pageSize);
        $entity->setPageOrientation('portrait');
        $entity->setItemsPerPage($itemsPerPage);
        $entity->setFilenameInfixes($filenameInfixes);
        $entity->setFilenamePrefix($filenamePrefix);
        $entity->setFilenameSuffix($filenameSuffix);
        $entity->setConfig([
            'companyName' => $companyName,
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => self::COMPANY_COUNTRY_ID,
        ]);

        if ($logoId !== null) {
            $entity->setLogoId($logoId);
            $entity->setLogo($this->createMedia($logoId));
        }

        if (!$global && $salesChannelId !== null) {
            $assignment = new DocumentBaseConfigSalesChannelEntity();
            $assignment->setUniqueIdentifier(Uuid::randomHex());
            $assignment->setId(Uuid::randomHex());
            $assignment->setSalesChannelId($salesChannelId);

            $entity->setSalesChannels(new DocumentBaseConfigSalesChannelCollection([$assignment]));
        } else {
            $entity->setSalesChannels(new DocumentBaseConfigSalesChannelCollection());
        }

        return $entity;
    }

    private function createCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setUniqueIdentifier(self::COMPANY_COUNTRY_ID);
        $country->setId(self::COMPANY_COUNTRY_ID);

        return $country;
    }

    /**
     * @return StaticEntityRepository<MediaCollection>
     */
    private function createMediaRepository(): StaticEntityRepository
    {
        $mediaRepository = new StaticEntityRepository(
            [new MediaCollection([
                $this->createMedia(self::COMPANY_INFO_LOGO_ID),
                $this->createMedia(self::LEGACY_LOGO_ID),
            ])],
            new MediaDefinition(),
        );

        return $mediaRepository;
    }

    private function createMedia(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);
        $media->setId($id);

        return $media;
    }

    /**
     * @param array<string, mixed>|null $companyInfo
     */
    private function createSystemConfigService(
        ?array $companyInfo = null,
        ?string $expectedSalesChannelId = null,
    ): SystemConfigService {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getDomain')
            ->willReturnCallback(function (string $domain, ?string $salesChannelId) use ($companyInfo, $expectedSalesChannelId): array {
                static::assertSame(self::COMPANY_INFO_CONFIG_DOMAIN, $domain);
                if ($expectedSalesChannelId !== null) {
                    static::assertSame($expectedSalesChannelId, $salesChannelId);
                }

                if ($companyInfo === null) {
                    return [];
                }

                return array_filter([
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyName' => $companyInfo['companyName'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyEmail' => $companyInfo['companyEmail'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyPhone' => $companyInfo['companyPhone'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyStreet' => $companyInfo['companyStreet'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyCountryId' => $companyInfo['companyCountryId'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyZipcode' => $companyInfo['companyZipcode'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyCity' => $companyInfo['companyCity'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyUrl' => $companyInfo['companyUrl'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyLogoId' => $companyInfo['companyLogoId'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyTaxNumber' => $companyInfo['companyTaxNumber'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyTaxOffice' => $companyInfo['companyTaxOffice'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyVatId' => $companyInfo['companyVatId'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyBankName' => $companyInfo['companyBankName'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyBankIban' => $companyInfo['companyBankIban'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyBankBic' => $companyInfo['companyBankBic'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyPlaceOfJurisdiction' => $companyInfo['companyPlaceOfJurisdiction'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyPlaceOfFulfillment' => $companyInfo['companyPlaceOfFulfillment'] ?? null,
                    self::COMPANY_INFO_CONFIG_PREFIX . 'companyExecutiveDirector' => $companyInfo['companyExecutiveDirector'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            });

        return $systemConfigService;
    }
}
