<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\App\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class AppDocumentTypeGenerationTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DocumentV2Trait;

    private const APP_NAME = 'swagIntegrationTestDocumentWarrantyApp';

    private const WARRANTY_DOCUMENT_TYPE = 'swag_warranty';

    private Connection $connection;

    private DocumentGenerator $documentGenerator;

    private MediaService $mediaService;

    /**
     * @var EntityRepository<DocumentFileCollection>
     */
    private EntityRepository $documentFileRepository;

    /**
     * @var EntityRepository<NumberRangeTypeCollection>
     */
    private EntityRepository $numberRangeTypeRepository;

    /**
     * @var EntityRepository<NumberRangeCollection>
     */
    private EntityRepository $numberRangeRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $this->buildDemoShippingAddress($shippingAddressId),
                ),
            ],
        );

        $this->connection = static::getContainer()->get(Connection::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->mediaService = static::getContainer()->get(MediaService::class);

        $this->documentFileRepository = static::getContainer()->get('document_file.repository');
        $this->numberRangeTypeRepository = static::getContainer()->get('number_range_type.repository');
        $this->numberRangeRepository = static::getContainer()->get('number_range.repository');

        $this->loadAppsFromDir(__DIR__ . '/_fixtures/DocumentWarrantyApp');

        static::getContainer()->get(DocumentTypeRegistry::class)->reset();
    }

    public function testInstallStoresAppFeatureAndSeedsNumberRange(): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `app_feature`.`type`, `app_feature`.`name`, `app_feature`.`payload` FROM `app_feature`
             INNER JOIN `app` ON `app`.`id` = `app_feature`.`app_id`
             WHERE `app`.`name` = :appName',
            ['appName' => self::APP_NAME],
        );

        static::assertIsArray($row);
        static::assertSame('document', $row['type']);
        static::assertSame(self::WARRANTY_DOCUMENT_TYPE, $row['name']);

        $payload = json_decode((string) $row['payload'], true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(self::WARRANTY_DOCUMENT_TYPE, $payload['identifier']);
        static::assertSame(['html', 'pdf'], $payload['formats']);
        static::assertSame('Warranty certificate', $payload['label']['en-GB'] ?? null);
        static::assertSame('a4', $payload['config']['pageSize'] ?? null);
        static::assertSame('portrait', $payload['config']['pageOrientation'] ?? null);
        static::assertSame(10, $payload['config']['itemsPerPage'] ?? null);

        $appFeatureStorage = static::getContainer()->get(AppFeatureStorage::class);
        $features = $appFeatureStorage->forActiveApps(AppDocumentTypeConfig::class);

        $warranty = null;
        foreach ($features as $feature) {
            if ($feature->config->getName() === self::WARRANTY_DOCUMENT_TYPE) {
                $warranty = $feature;
                break;
            }
        }

        static::assertNotNull($warranty);
        static::assertSame(self::APP_NAME, $warranty->appName);
        static::assertTrue($warranty->appActive);
        static::assertSame(['html', 'pdf'], $warranty->config->getFormats());

        $numberRangeTypeId = $this->numberRangeTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'document_swag_warranty')),
            $this->context,
        )->firstId();

        static::assertIsString($numberRangeTypeId);

        $numberRange = $this->numberRangeRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('typeId', $numberRangeTypeId)),
            $this->context,
        )->getEntities()->first();

        static::assertNotNull($numberRange);
        static::assertTrue($numberRange->get('global'));
        static::assertSame('{n}', $numberRange->getPattern());
        static::assertSame(1000, $numberRange->getStart());
    }

    public function testDocumentTypeRegistrySupportsTheAppRegisteredType(): void
    {
        $registry = static::getContainer()->get(DocumentTypeRegistry::class);

        static::assertTrue($registry->supports(self::WARRANTY_DOCUMENT_TYPE));
        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats(self::WARRANTY_DOCUMENT_TYPE));
        static::assertContains(self::WARRANTY_DOCUMENT_TYPE, $registry->getTechnicalNames());
    }

    public function testGeneratesAndPersistsAppDocumentAgainstTheSentinelType(): void
    {
        $this->seedCompanyInfo();

        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));
        $this->enrichOrderForRendering($orderId);

        $document = $this->documentGenerator->generate(
            new DocumentGenerationRequest(
                $orderId,
                self::WARRANTY_DOCUMENT_TYPE,
                [DocumentFormat::HTML, DocumentFormat::PDF],
                documentDate: self::DOCUMENT_DATE,
            ),
            $this->context,
        );

        $sentinelId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `document_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => 'app_provided'],
        );

        static::assertIsString($sentinelId);
        static::assertSame($sentinelId, $document->getDocumentTypeId());
        static::assertSame(self::WARRANTY_DOCUMENT_TYPE, $document->getTypeName());

        $files = $this->loadDocumentFiles($document->getId());
        static::assertCount(2, $files);

        $htmlFile = $files->filterByProperty('documentFormat', DocumentFormat::HTML->value)->first();
        static::assertInstanceOf(DocumentFileEntity::class, $htmlFile);

        $htmlContent = $this->mediaService->loadFile($htmlFile->getMediaId(), $this->context);
        static::assertNotEmpty($htmlContent);

        static::assertSame('text/html', (new \finfo(\FILEINFO_MIME_TYPE))->buffer($htmlContent));
        static::assertStringContainsString('Warranty', $htmlContent);

        $pdfFile = $files->filterByProperty('documentFormat', DocumentFormat::PDF->value)->first();
        static::assertInstanceOf(DocumentFileEntity::class, $pdfFile);

        $pdfContent = $this->mediaService->loadFile($pdfFile->getMediaId(), $this->context);
        static::assertNotEmpty($pdfContent);

        static::assertStringStartsWith('%PDF-', $pdfContent);
        static::assertSame('application/pdf', (new \finfo(\FILEINFO_MIME_TYPE))->buffer($pdfContent));
    }

    private function seedCompanyInfo(): void
    {
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->set('core.basicInformation.companyName', 'Example Company');
        $systemConfigService->set('core.basicInformation.companyStreet', 'Example Street 1');
        $systemConfigService->set('core.basicInformation.companyZipcode', '12345');
        $systemConfigService->set('core.basicInformation.companyCity', 'Example City');
        $systemConfigService->set('core.basicInformation.companyCountryId', $this->loadCompanyCountry()->getId());
    }

    private function loadDocumentFiles(string $documentId): DocumentFileCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('documentId', $documentId));

        return $this->documentFileRepository->search($criteria, $this->context)->getEntities();
    }
}
