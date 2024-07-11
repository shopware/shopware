<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionExportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\Exception\UpdatedByValueNotFoundException;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Test\ImportExport\StockSubscriber;
use Shopware\Core\Content\Test\ImportExport\TestSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\System\Unit\UnitCollection;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\System\Unit\UnitEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Customer\Rule\OrderFixture;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('services-settings')]
class ImportExportTest extends AbstractImportExportTestCase
{
    use OrderFixture;

    private bool $mediaDirCreated = false;

    private string $projectDir = '';
    private const PUBLIC_MEDIA_PATH = '/public/media';

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = static::getContainer()->getParameter('kernel.project_dir');

        if (!\is_dir($this->projectDir . self::PUBLIC_MEDIA_PATH)) {
            mkdir($this->projectDir . self::PUBLIC_MEDIA_PATH);
            $this->mediaDirCreated = true;
        }

        \copy(self::TEST_IMAGE, $this->projectDir . self::PUBLIC_MEDIA_PATH . '/ßhopware-logö.png');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \unlink(static::getContainer()->getParameter('kernel.project_dir') . '/public/media/ßhopware-logö.png');

        if ($this->mediaDirCreated) {
            rmdir(static::getContainer()->getParameter('kernel.project_dir') . '/public/media');
            $this->mediaDirCreated = false;
        }
    }

    public function testExportEvents(): void
    {
        $this->listener->addSubscriber(new StockSubscriber());

        $productId = Uuid::randomHex();
        $product = $this->getTestProduct($productId);
        $newStock = (int) $product['stock'] + 1;

        $criteria = new Criteria([$productId]);
        $progress = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $criteria);

        $events = array_column($this->listener->getCalledListeners(), 'event');
        static::assertContains(EnrichExportCriteriaEvent::class, $events);
        static::assertContains(ImportExportBeforeExportRecordEvent::class, $events);
        static::assertNotContains(ImportExportExceptionExportRecordEvent::class, $events);

        $csv = $this->getCsvContent($progress->getLogId());
        static::assertStringContainsString(sprintf(';%s;', $newStock), $csv);
    }

    public function testImportEvents(): void
    {
        $this->listener->addSubscriber(new TestSubscriber());
        $this->importCategoryCsv();
        $events = array_column($this->listener->getCalledListeners(), 'event');

        static::assertContains(ImportExportBeforeImportRecordEvent::class, $events);
        static::assertContains(ImportExportAfterImportRecordEvent::class, $events);
        static::assertNotContains(ImportExportExceptionImportRecordEvent::class, $events);
    }

    public function testImportExport(): void
    {
        $filesystem = static::getContainer()->get('shopware.filesystem.private');

        $productId = Uuid::randomHex();
        $this->getTestProduct($productId);
        $criteria = new Criteria([$productId]);
        $progress = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $progress = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $criteria);

        /** @var EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = static::getContainer()->get('import_export_file.repository');
        $criteria = new Criteria(array_filter([$this->getLogEntity($progress->getLogId())->getFileId()]));
        $file = $fileRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($file);
        $importExportFileEntity = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $importExportFileEntity);
        static::assertSame($filesystem->fileSize($importExportFileEntity->getPath()), $file->getSize());

        $this->productRepository->delete([['id' => $productId]], Context::createDefaultContext());
        $exportFileTmp = (string) tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, (string) $filesystem->read($file->getPath()));

        $progress = $this->import(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('categories');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($product);
    }

    #[Group('needsWebserver')]
    public function testMediaWithEncodedUrl(): void
    {
        $csvContent = sprintf('url
%s', EnvironmentHelper::getVariable('APP_URL')) . '/media/%C3%9Fhopware-log%C3%B6.png';

        $fixturesPath = __DIR__ . '/fixtures/media_encoded_url.csv';
        file_put_contents($fixturesPath, $csvContent);

        try {
            $progress = $this->import(Context::createDefaultContext(), MediaDefinition::ENTITY_NAME, $fixturesPath, 'media_encoded_url.csv', null, false, true);

            static::assertTrue($progress->isFinished());
            static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
        } finally {
            unlink($fixturesPath);
        }
    }

    public function testCategory(): void
    {
        $categoryRepository = static::getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $categories = [
            [
                'id' => $rootId,
                'name' => 'root',
            ],
            [
                'id' => $childId,
                'name' => 'child',
                'parentId' => $rootId,
            ],
        ];
        $categoryRepository->upsert($categories, Context::createDefaultContext());

        $betweenId = Uuid::randomHex();
        $categories = [
            [
                'id' => $betweenId,
                'name' => 'between',
            ],
            [
                'id' => $childId,
                'parentId' => $betweenId,
            ],
        ];
        $categoryRepository->upsert($categories, Context::createDefaultContext());

        $filesystem = static::getContainer()->get('shopware.filesystem.private');

        $criteria = new Criteria([$rootId, $betweenId, $childId]);
        $progress = $this->export(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $criteria);
        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $progress = $this->export(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $criteria);

        $categoryRepository->delete([['id' => $childId], ['id' => $betweenId], ['id' => $rootId]], Context::createDefaultContext());

        $exportFileTmp = (string) tempnam(sys_get_temp_dir(), '');

        $logFile = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $logFile);
        file_put_contents($exportFileTmp, (string) $filesystem->read($logFile->getPath()));

        $this->import(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $ids = $categoryRepository->searchIds(new Criteria([$rootId, $betweenId, $childId]), Context::createDefaultContext());
        static::assertCount(3, $ids->getIds());
        static::assertTrue($ids->has($rootId));
        static::assertTrue($ids->has($betweenId));
        static::assertTrue($ids->has($childId));
    }

    public function testSortingShouldWorkAsExpected(): void
    {
        $profileRepository = static::getContainer()->get('import_export_profile.repository');

        $profile = $this->createCategoryProfileMock();
        $profileRepository->create([$profile], Context::createDefaultContext());

        $filesystem = static::getContainer()->get('shopware.filesystem.private');

        $criteria = new Criteria();
        $progress = $this->export(
            Context::createDefaultContext(),
            CategoryDefinition::ENTITY_NAME,
            $criteria,
            null,
            $profile['id']
        );
        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $exportFile = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertNotNull($exportFile);
        $fileContents = $filesystem->read($exportFile->getPath());

        $regexResult = preg_split('#\r?\n#', $fileContents);
        static::assertIsArray($regexResult);
        $firstLine = $regexResult[0];
        $csvColumns = explode(';', $firstLine);

        $sortedMappings = $profile['mapping'];
        usort($sortedMappings, fn ($firstMapping, $secondMapping) => $firstMapping['position'] - $secondMapping['position']);

        foreach ($sortedMappings as $index => $mapping) {
            static::assertSame(
                $mapping['mappedKey'],
                trim($csvColumns[$index]),
                'Keys should have the same name. It may be that the sorting is broken.'
            );
        }
    }

    public function testNewsletterRecipient(): void
    {
        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        $testData = [
            'id' => Uuid::randomHex(),
            'salutation' => [
                'id' => Uuid::randomHex(),
                'salutationKey' => 'test',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'displayName' => 'test display name',
                        'letterName' => 'test letter name',
                    ],
                ],
            ],
            'email' => 'foo.bar@example.test',
            'title' => 'dr.',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'zipCode' => '48599',
            'city' => 'Musterstadt',
            'street' => 'Musterstraße 7',
            'hash' => 'asdf',
            'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
            'confirmedAt' => new \DateTimeImmutable('2020-02-29 13:37'),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
        $repo = static::getContainer()->get('newsletter_recipient.repository');

        $context = Context::createDefaultContext();
        $repo->upsert([$testData], $context);

        $criteria = new Criteria([$testData['id']]);
        $progress = $this->export($context, NewsletterRecipientDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $repo->delete([['id' => $testData['id']]], Context::createDefaultContext());

        $exportFileTmp = (string) tempnam(sys_get_temp_dir(), '');
        $logfile = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $logfile);
        file_put_contents($exportFileTmp, (string) $filesystem->read($logfile->getPath()));

        $progress = $this->import($context, NewsletterRecipientDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $actualNewsletter = $repo->search(new Criteria([$testData['id']]), Context::createDefaultContext());
        static::assertNotNull($actualNewsletter);
    }

    public function testImportExportAdvancedPrices(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $productId = 'e12a77e8ed6642698b987250d8ec705d';
        $ruleId = 'cb34dc6f20b6479aa975e1290f442e65';
        $this->createProduct($productId);
        $this->createRule($ruleId);

        $progress = $this->import($context, ProductPriceDefinition::ENTITY_NAME, '/fixtures/advanced_prices.csv', 'advanced_prices.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $product = $this->productRepository->search((new Criteria([$productId]))->addAssociation('prices'), $context)->getEntities()->first();
        static::assertNotNull($product);

        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());
        $firstPrice = $product->getPrices()->first();

        static::assertInstanceOf(ProductPriceEntity::class, $firstPrice);
        static::assertSame($ruleId, $firstPrice->getRuleId());
        static::assertInstanceOf(Price::class, $firstPrice->getPrice()->first());
        static::assertSame(7.89, $firstPrice->getPrice()->first()->getNet());
        static::assertSame(9.39, $firstPrice->getPrice()->first()->getGross());
        static::assertSame(1, $firstPrice->getQuantityStart());
        static::assertSame(10, $firstPrice->getQuantityEnd());
        $lastPrice = $product->getPrices()->last();
        static::assertInstanceOf(ProductPriceEntity::class, $lastPrice);
        static::assertSame($ruleId, $lastPrice->getRuleId());
        static::assertInstanceOf(Price::class, $lastPrice->getPrice()->first());
        static::assertSame(5.67, $lastPrice->getPrice()->first()->getNet());
        static::assertSame(6.75, $lastPrice->getPrice()->first()->getGross());
        static::assertSame(11, $lastPrice->getQuantityStart());
        static::assertNull($lastPrice->getQuantityEnd());

        $progress = $this->export($context, ProductPriceDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress);

        $csv = $this->getCsvContent($progress->getLogId());

        static::assertStringEqualsFile(__DIR__ . '/fixtures/advanced_prices.csv', $csv);
    }

    public function importCategoryCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, CategoryDefinition::ENTITY_NAME, '/fixtures/categories.csv', 'categories.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }

    public function importPropertyCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, PropertyGroupOptionDefinition::ENTITY_NAME, '/fixtures/properties.csv', 'properties.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }

    public function importPropertyCsvWithoutIds(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, PropertyGroupOptionDefinition::ENTITY_NAME, '/fixtures/propertieswithoutid.csv', 'properties.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $propertyRepository = static::getContainer()->get('property_group_option.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'alicebluenew'));
        $property = $propertyRepository->search($criteria, $context);
        static::assertCount(1, $property);
    }

    public function importPropertyWithDefaultsCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(PropertyGroupOptionDefinition::ENTITY_NAME);
        static::assertIsArray($clonedPropertyProfile->getMapping());
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            static::assertIsArray($mappings[$key]);
            if ($mappings[$key]['mappedKey'] === 'name') {
                $mappings[$key]['useDefaultValue'] = true;
                $mappings[$key]['defaultValue'] = 'MyDefaultNameForProperties';

                break;
            }
        }
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);

        $progress = $this->import(
            $context,
            PropertyGroupOptionDefinition::ENTITY_NAME,
            '/fixtures/properties_with_empty_names.csv',
            'properties.csv',
            $clonedPropertyProfile->getId()
        );

        // import should succeed even if required names are empty (they will be replaced by default values)
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $propertyRepository = static::getContainer()->get('property_group_option.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'MyDefaultNameForProperties'));
        $property = $propertyRepository->search($criteria, $context);
        // import should create 7 properties with default name
        static::assertCount(7, $property);
    }

    public function importPropertyWithUserRequiredCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(PropertyGroupOptionDefinition::ENTITY_NAME);
        static::assertIsArray($clonedPropertyProfile->getMapping());
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            static::assertIsArray($mappings[$key]);
            if ($mappings[$key]['mappedKey'] === 'media_url') {
                $mappings[$key]['requiredByUser'] = true;

                break;
            }
        }
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);

        $progress = $this->import(
            $context,
            PropertyGroupOptionDefinition::ENTITY_NAME,
            '/fixtures/properties.csv',
            'properties.csv',
            $clonedPropertyProfile->getId()
        );

        // import should fail even if all system required fields are set,
        // there are rows that have no values for user required fields.
        // Input CSV is the same as in the 'importPropertyCsv' test (which previously succeeded here).
        static::assertImportExportFailed($progress);
        static::assertSame(0, $progress->getProcessedRecords());

        // check the errors
        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertGreaterThanOrEqual(1, \count($invalid)); // there could already be other errors
        $first = $invalid[0];
        static::assertStringContainsString('media_url is set to required by the user but has no value', $first['_error']);
    }

    #[Group('slow')]
    public function testProductsCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->importCategoryCsv();
        $this->importPropertyCsv();
        $this->importPropertyCsvWithoutIds();

        $this->importPropertyWithDefaultsCsv();
        $this->importPropertyWithUserRequiredCsv();

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria();
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('configuratorSettings');
        $criteria->addFilter(new EqualsFilter('parentId', 'e5c8b8f701034e8dbea72ac0fc32521e'));

        $firstProduct = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($firstProduct);

        static::assertCount(2, $firstProduct->getVariation());

        $criteria->resetFilters();
        $criteria->addFilter(new EqualsFilter('id', 'e5c8b8f701034e8dbea72ac0fc32521e'));

        $secondProduct = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($secondProduct);

        static::assertInstanceOf(ProductConfiguratorSettingCollection::class, $secondProduct->getConfiguratorSettings());
        static::assertCount(2, $secondProduct->getConfiguratorSettings());
    }

    public function testProductsCoverIsUpdated(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_updated_cover.csv', 'products.csv');

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $product = $this->productRepository->search(
            (new Criteria(['e5c8b8f701034e8dbea72ac0fc32521e']))->addAssociation('media'),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertNotNull($product);

        static::assertInstanceOf(ProductMediaCollection::class, $product->getMedia());
        static::assertCount(1, $product->getMedia());
    }

    #[Group('slow')]
    public function testProductsWithVariantsCsv(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `product`');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_variants.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
        static::assertSame(2, $progress->getProcessedRecords());

        $criteria = new Criteria();
        $criteria->addAssociation('options.group');
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('parentId', null)]));

        $products = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(32, $products);
        static::assertInstanceOf(ProductEntity::class, $product = $products->first());
        static::assertCount(3, $product->getVariation());
        static::assertContains('color', array_column($product->getVariation(), 'group'));
        static::assertContains('size', array_column($product->getVariation(), 'group'));
        static::assertContains('material', array_column($product->getVariation(), 'group'));

        $criteria = new Criteria();
        $criteria->addAssociation('configuratorSettings');
        $criteria->addFilter(new EqualsFilter('parentId', null));

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductConfiguratorSettingCollection::class, $settings = $product->getConfiguratorSettings());
        static::assertCount(10, $settings);
    }

    #[Group('slow')]
    public function testProductsWithInvalidVariantsCsv(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `product`');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_invalid_variants.csv', 'products.csv');

        static::assertImportExportFailed($progress);

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertCount(2, $invalid);

        $first = $invalid[0];
        static::assertStringContainsString('size: M, L, XL, XXL | oops', $first['_error']);
        $second = $invalid[1];
        static::assertStringContainsString('size: , | color: Green, White, Black, Purple', $second['_error']);
    }

    public function testProductsWithOwnIdentifier(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $productIds = [
            Uuid::fromStringToHex('product1'),
            Uuid::fromStringToHex('product2'),
            Uuid::fromStringToHex('product3'),
            Uuid::fromStringToHex('product4'),
        ];

        $categoryRepository = static::getContainer()->get('category.repository');
        $category1Id = Uuid::fromStringToHex('category1');
        $category2Id = '0a600a2648b3486fbfdbc60993050103';
        $category3Id = Uuid::fromStringToHex('category3');
        $categoryRepository->upsert([
            [
                'id' => $category1Id,
                'name' => 'First category',
            ],

            [
                'id' => $category2Id,
                'name' => 'Second category',
            ],

            [
                'id' => $category3Id,
                'name' => 'Third category',
            ],
        ], $context);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_own_identifier.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $count = $this->productRepository->search(new Criteria($productIds), $context)->count();
        static::assertSame(4, $count);

        $name = 'Name has changed';
        $this->productRepository->upsert([
            [
                'id' => $productIds[0],
                'name' => $name,
            ],
        ], $context);

        $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_own_identifier.csv', 'products.csv');

        $criteria = new Criteria([$productIds[0]]);
        $criteria->addAssociation('categories');

        $product = $this->productRepository->search($criteria, $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotSame($name, $product->getName());
        static::assertSame(Uuid::fromStringToHex('tax19'), $product->getTaxId());
        static::assertSame(Uuid::fromStringToHex('manufacturer1'), $product->getManufacturerId());

        static::assertInstanceOf(CategoryCollection::class, $categories = $product->getCategories());
        static::assertCount(3, $categories);
        static::assertTrue($categories->has($category1Id));
        static::assertTrue($categories->has($category2Id));
        static::assertTrue($categories->has($category3Id));
    }

    public function testProductsWithCategoryPaths(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryHome = Uuid::fromStringToHex('home');
        $categoryHomeFirst = Uuid::fromStringToHex('homeFirst');
        $categoryHomeSecond = Uuid::fromStringToHex('homeSecond');
        $categoryHomeFirstSecond = Uuid::fromStringToHex('homeFirstSecond');
        $categoryHomeFirstNewSecondNew = Uuid::fromStringToHex('Main>First New>Second New');

        $categoryRepository->upsert([
            [
                'id' => $categoryHome,
                'name' => 'Main',
            ],

            [
                'id' => $categoryHomeFirst,
                'name' => 'First',
                'parentId' => $categoryHome,
            ],

            [
                'id' => $categoryHomeFirstSecond,
                'name' => 'Second',
                'parentId' => $categoryHomeFirst,
            ],

            [
                'id' => $categoryHomeSecond,
                'name' => 'Second',
                'parentId' => $categoryHome,
            ],
        ], $context);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_category_path.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria([Uuid::fromStringToHex('meinhappyproduct')]);
        $criteria->addAssociation('categories');

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($product);

        $categories = $product->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $categories);
        static::assertCount(4, $categories);
        static::assertTrue($categories->has($categoryHome));
        static::assertTrue($categories->has($categoryHomeFirstSecond));
        static::assertTrue($categories->has($categoryHomeSecond));
        static::assertTrue($categories->has($categoryHomeFirstNewSecondNew));

        $newCategoryLeaf = $categories->get($categoryHomeFirstNewSecondNew);
        static::assertSame(Uuid::fromStringToHex('Main>First New'), $newCategoryLeaf->getParentId());
    }

    #[DataProvider('coverMediaUrlDataProvider')]
    public function testImportProductsWithCoverMediaUrl(string $imageName, string $queryParameter): void
    {
        $productName = 'product_with_cover_media_url';
        $csvFileName = 'product_with_cover_media_url.csv';
        $expectedExtension = 'png';

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $filesystem = new Filesystem();
        $filesystem->copy(
            __DIR__ . '/fixtures/' . $imageName,
            $this->projectDir . self::PUBLIC_MEDIA_PATH . '/' . $imageName
        );

        $csvContent = sprintf(
            'product_number;stock;name;price_net;price_gross;tax_id;tax_rate;tax_name;cover_media_url
SWTEST;1;' . $productName . ';9.35;10;0c17372fe6aa46059a97fc28b40f46c4;7;7%%;%s',
            EnvironmentHelper::getVariable('APP_URL') . '/media/' . $imageName . $queryParameter
        );

        $filesystem->dumpFile(__DIR__ . '/fixtures/' . $csvFileName, $csvContent);

        try {
            static::assertTrue($filesystem->exists($this->projectDir . self::PUBLIC_MEDIA_PATH . '/' . $imageName));
            static::assertTrue($filesystem->exists(__DIR__ . '/fixtures/' . $csvFileName));

            $progress = $this->import(
                $context,
                ProductDefinition::ENTITY_NAME,
                __DIR__ . '/fixtures/' . $csvFileName,
                $csvFileName,
                null,
                false,
                true
            );

            static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $productName));
            $criteria->addAssociation('cover');
            $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
            static::assertInstanceOf(ProductEntity::class, $product);

            $productMedia = $product->getCover();
            static::assertInstanceOf(ProductMediaEntity::class, $productMedia);
            $media = $productMedia->getMedia();
            static::assertInstanceOf(MediaEntity::class, $media);
            static::assertSame($imageName, $media->getFileName());
            static::assertSame($expectedExtension, $media->getFileExtension());
        } finally {
            $filesystem->remove([
                __DIR__ . '/fixtures/' . $csvFileName,
                $this->projectDir . self::PUBLIC_MEDIA_PATH . '/' . $imageName,
            ]);
        }
    }

    public static function coverMediaUrlDataProvider(): \Generator
    {
        yield 'import coverMediaUrl without extension' => [
            'test-image-without-extension',
            '',
        ];
        yield 'import coverMediaUrl without extension and query parameters' => [
            'test-image-without-extension',
            '?some=query&params=here',
        ];
    }

    public function testInvalidFile(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `product`');

        $progress = $this->import(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, '/fixtures/products_with_invalid.csv', 'products.csv');

        static::assertImportExportFailed($progress);

        $ids = $this->productRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(8, $ids->getIds());

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertCount(2, $invalid);

        $first = $invalid[0];
        static::assertSame('e5c8b8f701034e8dbea72ac0fc32521e', $first['id']);
        static::assertStringContainsString('CONSTRAINT `fk.product_', $first['_error']);

        $second = $invalid[1];
        static::assertSame('d5e8a6d00ce64f369a6aa3e29c4650cf', $second['id']);
        static::assertStringContainsString('CONSTRAINT `fk.product_', $second['_error']);
    }

    public function testFinishedImportDoesNothing(): void
    {
        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::never())->method('append');

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::never())->method('in');
        $pipe->expects(static::never())->method('out');

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'file' => (new ImportExportFileEntity())->assign([
                'path' => 'foobar', 'size' => 1337,
            ]),
            'records' => 5,
        ]);

        $importExportService = $this->createMock(ImportExportService::class);
        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            static::getContainer()->get('shopware.filesystem.private'),
            $this->createMock(EventDispatcherInterface::class),
            static::getContainer()->get(Connection::class),
            $this->createMock(EntityRepository::class),
            $pipe,
            $reader,
            $writer,
            static::getContainer()->get(FileService::class),
        );

        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn () => new Progress($logEntity->getId(), $logEntity->getState())
            );

        $logEntity->setState(Progress::STATE_SUCCEEDED);
        $importExport->import(Context::createDefaultContext());
        $importExport->export(Context::createDefaultContext(), new Criteria());

        $logEntity->setState(Progress::STATE_ABORTED);
        $importExport->import(Context::createDefaultContext());
        $importExport->export(Context::createDefaultContext(), new Criteria());

        $logEntity->setState(Progress::STATE_FAILED);
        $importExport->import(Context::createDefaultContext());
        $importExport->export(Context::createDefaultContext(), new Criteria());
    }

    public function testDryRunImport(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->rollBack();
        $connection->executeStatement('DELETE FROM `product`');

        $clonedProductProfile = $this->cloneDefaultProfile(ProductDefinition::ENTITY_NAME);
        static::assertIsArray($clonedProductProfile->getMapping());
        $mappings = $clonedProductProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            static::assertIsArray($mappings[$key]);
            if ($mappings[$key]['mappedKey'] === 'description') {
                $mappings[$key]['requiredByUser'] = true;

                break;
            }
        }
        $this->updateProfileMapping($clonedProductProfile->getId(), $mappings);

        $progress = $this->import(
            Context::createDefaultContext(),
            ProductDefinition::ENTITY_NAME,
            '/fixtures/products_with_invalid_dryrun.csv',
            'products.csv',
            $clonedProductProfile->getId(),
            true
        );
        static::assertImportExportFailed($progress);

        $ids = $this->productRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $ids->getIds());

        $result = $this->getLogEntity($progress->getLogId())->getResult();
        static::assertSame(2, $result['product_category']['insertSkip']);
        static::assertSame(8, $result['product']['insert']);
        static::assertSame(1, $result['product']['otherError']);

        $connection->executeStatement('DELETE FROM `import_export_log`');
        $connection->executeStatement('DELETE FROM `import_export_file`');
        $connection->executeStatement(
            'DELETE FROM `import_export_profile` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($clonedProductProfile->getId())]
        );
        $connection->beginTransaction();
    }

    public function testProductWithListPrice(): void
    {
        $context = Context::createDefaultContext();
        $profile = $this->cloneDefaultProfile(ProductDefinition::ENTITY_NAME);

        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'price.DEFAULT.listPrice.linked',
            'mappedKey' => 'list_price_linked',
        ];
        $mapping[] = [
            'key' => 'price.DEFAULT.listPrice.gross',
            'mappedKey' => 'list_price_gross',
        ];
        $mapping[] = [
            'key' => 'price.DEFAULT.listPrice.net',
            'mappedKey' => 'list_price_net',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_list_price.csv', 'products_with_list_price.csv', $profile->getId());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $result = $this->productRepository->search(new Criteria(), Context::createDefaultContext());

        static::assertCount(2, $result);
        $products = $result->getEntities();

        static::assertTrue($products->has('bf44b430d7cd47fcac93310edf4fe4e1'));
        $firstProduct = $products->get('bf44b430d7cd47fcac93310edf4fe4e1');
        static::assertInstanceOf(PriceCollection::class, $firstProduct->getPrice());
        static::assertInstanceOf(Price::class, $firstProduct->getPrice()->first());
        $firstListPrice = $firstProduct->getPrice()->first()->getListPrice();
        static::assertInstanceOf(Price::class, $firstListPrice);
        static::assertSame(100.0, $firstListPrice->getNet());
        static::assertSame(5000.0, $firstListPrice->getGross());
        static::assertFalse($firstListPrice->getLinked());

        static::assertTrue($products->has('bf44b430d7cd47fcac93310edf4fe4e2'));
        $secondProduct = $products->get('bf44b430d7cd47fcac93310edf4fe4e2');
        static::assertInstanceOf(PriceCollection::class, $secondProduct->getPrice());
        static::assertInstanceOf(Price::class, $secondProduct->getPrice()->first());
        $secondListPrice = $secondProduct->getPrice()->first()->getListPrice();
        static::assertInstanceOf(Price::class, $secondListPrice);
        static::assertSame(20.0, $secondListPrice->getNet());
        static::assertSame(50.0, $secondListPrice->getGross());
        static::assertTrue($secondListPrice->getLinked());
    }

    public function testProductImportExportWithCustomField(): void
    {
        $context = Context::createDefaultContext();
        $profile = $this->cloneDefaultProfile(ProductDefinition::ENTITY_NAME);

        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_1',
            'mappedKey' => 'custom_field_1',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_2',
            'mappedKey' => 'custom_field_2',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_3',
            'mappedKey' => 'custom_field_3',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_4',
            'mappedKey' => 'custom_field_4',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_5',
            'mappedKey' => 'custom_field_5',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields',
            'mappedKey' => 'custom_fields',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $this->createCustomField([
            [
                'name' => 'custom_field_1',
                'type' => 'string',
            ],
            [
                'name' => 'custom_field_2',
                'type' => 'int',
            ],
            [
                'name' => 'custom_field_3',
                'type' => 'bool',
            ],
            [
                'name' => 'custom_field_4',
                'type' => 'datetime',
            ],
            [
                'name' => 'custom_field_5',
                'type' => 'select',
            ],
            [
                'name' => 'custom_field_6',
                'type' => 'string',
            ],
        ], ProductDefinition::ENTITY_NAME);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_custom_fields.csv', 'products_with_custom_fields.csv', $profile->getId());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $product = $this->productRepository->search(new Criteria(['e5c8b8f701034e8dbea72ac0fc32521e']), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($product);

        static::assertIsArray($product->getCustomFields());
        static::assertSame('foo', $product->getCustomFields()['custom_field_1']);
        static::assertSame(23, $product->getCustomFields()['custom_field_2']);
        static::assertTrue($product->getCustomFields()['custom_field_3']);
        static::assertSame('2021-12-12T12:00:00+00:00', $product->getCustomFields()['custom_field_4']);
        static::assertSame(['abc8b8f701034e8dbea72ac0fc32521e', 'c5c8b8f701034e8dbea72ac0fc32521e'], $product->getCustomFields()['custom_field_5']);

        $progress = $this->export($context, ProductDefinition::ENTITY_NAME, null, null, $profile->getId());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());
        $resource = fopen('data://text/plain;base64,' . base64_encode($csv), 'r');
        static::assertIsResource($resource);
        $reader = new CsvReader();
        $record = null;
        foreach ($reader->read(new Config([], [], []), $resource, 0) as $row) {
            $record = $row;

            break;
        }

        static::assertNotNull($record);
        static::assertSame('foo', $record['custom_field_1']);
        static::assertSame('23', $record['custom_field_2']);
        static::assertSame('1', $record['custom_field_3']);
        static::assertSame('2021-12-12T12:00:00+00:00', $record['custom_field_4']);
        static::assertSame('["abc8b8f701034e8dbea72ac0fc32521e","c5c8b8f701034e8dbea72ac0fc32521e"]', $record['custom_field_5']);
        static::assertSame(
            '{"custom_field_1":"foo","custom_field_2":23,"custom_field_3":true,"custom_field_4":"2021-12-12T12:00:00+00:00","custom_field_5":["abc8b8f701034e8dbea72ac0fc32521e","c5c8b8f701034e8dbea72ac0fc32521e"],"custom_field_6":"bar"}',
            $record['custom_fields']
        );
    }

    #[DataProvider('salesChannelAssignmentCsvProvider')]
    public function testSalesChannelAssignment(string $csvPath): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `product`');
        $connection->executeStatement('DELETE FROM `product_visibility`');

        $productAId = 'a5c8b8f701034e8dbea72ac0fc32521e';
        $productABId = 'abc8b8f701034e8dbea72ac0fc32521e';
        $productCId = 'c5c8b8f701034e8dbea72ac0fc32521e';

        $salesChannelAId = 'a8432def39fc4624b33213a56b8c944d';
        $this->createSalesChannel([
            'id' => $salesChannelAId,
            'name' => 'First Sales Channel',
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);

        $salesChannelBId = 'b8432def39fc4624b33213a56b8c944d';
        $this->createSalesChannel([
            'id' => $salesChannelBId,
            'name' => 'Second Sales Channel',
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/b',
            ]],
        ]);

        $progress = $this->import(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $csvPath, 'products_with_visibilities.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria([$productAId]);
        $criteria->addAssociation('visibilities');

        $productA = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ProductEntity::class, $productA);

        static::assertInstanceOf(ProductVisibilityCollection::class, $productA->getVisibilities());
        static::assertCount(1, $productA->getVisibilities());
        static::assertNotNull($productA->getVisibilities()->filterBySalesChannelId($salesChannelAId)->first());

        $criteria = new Criteria([$productABId]);
        $criteria->addAssociation('visibilities');

        $productB = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ProductEntity::class, $productB);

        static::assertInstanceOf(ProductVisibilityCollection::class, $productB->getVisibilities());
        static::assertCount(2, $productB->getVisibilities());
        static::assertNotNull($productB->getVisibilities()->filterBySalesChannelId($salesChannelAId)->first());
        static::assertNotNull($productB->getVisibilities()->filterBySalesChannelId($salesChannelBId)->first());

        $criteria = new Criteria([$productCId]);
        $criteria->addAssociation('visibilities');

        $productC = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ProductEntity::class, $productC);

        static::assertInstanceOf(ProductVisibilityCollection::class, $productC->getVisibilities());
        static::assertCount(0, $productC->getVisibilities());
        static::assertNull($productC->getVisibilities()->filterBySalesChannelId($salesChannelAId)->first());
        static::assertNull($productC->getVisibilities()->filterBySalesChannelId($salesChannelBId)->first());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function salesChannelAssignmentCsvProvider(): array
    {
        return [
            ['/fixtures/products_with_visibilities.csv'],
            ['/fixtures/products_with_visibility_names.csv'],
        ];
    }

    #[Group('slow')]
    public function testCrossSellingCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $csvPath = '/fixtures/cross_selling_products_with_own_identifier.csv';
        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, $csvPath, 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csvPath = '/fixtures/cross_selling_with_own_identifier.csv';
        $progress = $this->import($context, ProductCrossSellingDefinition::ENTITY_NAME, $csvPath, 'cross_selling_with_own_identifier.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria(['cf682b73be1afad47d0f32559ac34627', 'c9a70321b66449abb54ba9306ad02835']);
        $criteria->addAssociation('crossSellings.assignedProducts');

        $productA = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->get('cf682b73be1afad47d0f32559ac34627');
        static::assertNotNull($productA);
        $productB = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->get('c9a70321b66449abb54ba9306ad02835');
        static::assertNotNull($productB);

        static::assertInstanceOf(ProductCrossSellingCollection::class, $productA->getCrossSellings());
        $aCrossSelling = $productA->getCrossSellings()->first();
        static::assertInstanceOf(ProductCrossSellingEntity::class, $aCrossSelling);
        static::assertSame('Lorem', $aCrossSelling->getName());
        static::assertInstanceOf(ProductCrossSellingAssignedProductsCollection::class, $aCrossSelling->getAssignedProducts());
        static::assertCount(3, $aCrossSelling->getAssignedProducts());

        static::assertInstanceOf(ProductCrossSellingCollection::class, $productB->getCrossSellings());
        $bCrossSelling = $productB->getCrossSellings()->first();
        static::assertInstanceOf(ProductCrossSellingEntity::class, $bCrossSelling);
        static::assertSame('Ipsum', $bCrossSelling->getName());
        static::assertInstanceOf(ProductCrossSellingAssignedProductsCollection::class, $bCrossSelling->getAssignedProducts());
        static::assertCount(3, $bCrossSelling->getAssignedProducts());

        $progress = $this->export($context, ProductCrossSellingDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());

        static::assertStringContainsString(
            'f26b0d8f252a76f2f99337cced08314b|c1ace7586faa4342a4d3b33e6dd33b7c|c9a70321b66449abb54ba9306ad02835',
            $csv
        );

        static::assertStringContainsString(
            'c1ace7586faa4342a4d3b33e6dd33b7c|f26b0d8f252a76f2f99337cced08314b|cf682b73be1afad47d0f32559ac34627',
            $csv
        );
    }

    #[Group('slow')]
    public function testCustomersCsv(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `customer`');

        $salesChannel = $this->createSalesChannel();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $mailSent = false;

        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $listenerClosure = function () use (&$mailSent): void {
            $mailSent = true;
        };

        $this->addEventListener($eventDispatcher, MailSentEvent::class, $listenerClosure);

        $progress = $this->import($context, CustomerDefinition::ENTITY_NAME, '/fixtures/customers.csv', 'customers.csv');
        $eventDispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));
        static::assertFalse($mailSent, 'The mail.sent Event did run');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria();
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');
        /** @var EntityRepository<CustomerCollection> $repository */
        $repository = static::getContainer()->get('customer.repository');
        $result = $repository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(3, $result);

        static::assertTrue($result->has('0a1dea4bd2de43929ac210fd17339dde'));
        $customerWithMultipleAddresses = $result->get('0a1dea4bd2de43929ac210fd17339dde');

        static::assertInstanceOf(CustomerAddressCollection::class, $customerWithMultipleAddresses->getAddresses());
        static::assertCount(4, $customerWithMultipleAddresses->getAddresses());
        static::assertInstanceOf(CustomerAddressEntity::class, $customerWithMultipleAddresses->getDefaultBillingAddress());
        static::assertSame('shopware AG', $customerWithMultipleAddresses->getDefaultBillingAddress()->getCompany());

        static::assertTrue($result->has('f3bb913bc8cc48479c3834a75e82920b'));
        $customerWithUpdatedAddresses = $result->get('f3bb913bc8cc48479c3834a75e82920b');

        static::assertInstanceOf(CustomerAddressCollection::class, $customerWithUpdatedAddresses->getAddresses());
        static::assertCount(2, $customerWithUpdatedAddresses->getAddresses());
        static::assertInstanceOf(CustomerAddressEntity::class, $customerWithUpdatedAddresses->getDefaultShippingAddress());
        static::assertSame('shopware AG', $customerWithUpdatedAddresses->getDefaultShippingAddress()->getCompany());

        $progress = $this->export($context, CustomerDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());
        static::assertStringContainsString($salesChannel['name'], $csv);
        static::assertStringContainsString('shopware AG', $csv);
        static::assertStringContainsString('en-GB', $csv);
        static::assertStringContainsString('Standard customer group', $csv);
    }

    public function testImportWithCreateAndUpdateConfig(): void
    {
        // expect default upsert
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => true,
            'updateEntities' => true,
        ]);
        static::assertSame(5, $mockRepo->upsertCalls);
        static::assertSame(0, $mockRepo->createCalls);
        static::assertSame(0, $mockRepo->updateCalls);

        // expect create
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => true,
            'updateEntities' => false,
        ]);
        static::assertSame(0, $mockRepo->upsertCalls);
        static::assertSame(5, $mockRepo->createCalls);
        static::assertSame(0, $mockRepo->updateCalls);

        // expect update
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => false,
            'updateEntities' => true,
        ]);
        static::assertSame(0, $mockRepo->upsertCalls);
        static::assertSame(0, $mockRepo->createCalls);
        static::assertSame(5, $mockRepo->updateCalls);

        // expect upsert if both flags are false
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => false,
            'updateEntities' => false,
        ]);
        static::assertSame(5, $mockRepo->upsertCalls);
        static::assertSame(0, $mockRepo->createCalls);
        static::assertSame(0, $mockRepo->updateCalls);
    }

    public function testCustomerImportExportWithBoundSalesChannel(): void
    {
        $customerRepository = self::getContainer()->get('customer.repository');
        $customers = $customerRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $customers);

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $profile = $this->cloneDefaultProfile(CustomerDefinition::ENTITY_NAME);
        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'boundSalesChannelId',
            'mappedKey' => 'boundsaleschannel_id',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $salesChannel = $this->createSalesChannel([
            'id' => '01902502a01172ad948f5a50096da0bd',
            'name' => 'Sales-Channel-Name',
        ]);

        $progress = $this->import(
            $context,
            CustomerDefinition::ENTITY_NAME,
            '/fixtures/customers_boundsaleschannel.csv',
            'customers_boundsaleschannel.csv',
            $profile->getId()
        );

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $customers = $customerRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertCount(1, $customers);
        $customer = $customers->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);

        $boundSalesChannelId = $customer->getBoundSalesChannelId();
        static::assertIsString($boundSalesChannelId);
        static::assertSame($salesChannel['id'], $boundSalesChannelId);

        $progress = $this->export($context, CustomerDefinition::ENTITY_NAME, null, null, $profile->getId());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csvContent = $this->getCsvContent($progress->getLogId());
        static::assertStringContainsString($salesChannel['id'], $csvContent);
    }

    public function testPromotionCodeImportExport(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `promotion_individual_code`');

        // create the promotion before the import
        $promotion = $this->createPromotion([
            'id' => 'c1a28776116d4431a2208eb2960ec340',
            'name' => 'MyPromo',
        ]);

        // add one already generated code to the promotion
        // already existing codes can only be updated by import
        // -> code is unique
        $this->createPromotionCode($promotion['id'], [
            'code' => 'TestCode',
        ]);

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, PromotionIndividualCodeDefinition::ENTITY_NAME, '/fixtures/promotion_individual_codes.csv', 'promotion_individual_codes.csv');

        // validate import
        static::assertImportExportFailed($progress);

        $failingRecords = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());
        static::assertCount(4, $failingRecords);

        /** @var EntityRepository<PromotionIndividualCodeCollection> $repository */
        $repository = static::getContainer()->get('promotion_individual_code.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('promotion');
        $individualCodes = $repository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(10, $individualCodes);

        foreach ($individualCodes as $promoCodeResult) {
            static::assertInstanceOf(PromotionEntity::class, $promoCodeResult->getPromotion());
            static::assertTrue($promoCodeResult->getPromotion()->isUseIndividualCodes(), 'Promotion should have useIndividualCodes set to true after import');
        }

        // export
        $progress = $this->export($context, PromotionIndividualCodeDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());

        // validate export
        foreach ($individualCodes as $promoCodeResult) {
            static::assertStringContainsString($promoCodeResult->getId(), $csv);
            static::assertInstanceOf(PromotionEntity::class, $promoCodeResult->getPromotion());
            static::assertStringContainsString($promoCodeResult->getPromotion()->getId(), $csv);
            static::assertStringContainsString($promoCodeResult->getCode(), $csv);
        }
    }

    public function testPromotionDiscountImportExport(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $promotionId = '6081116ad83747b9b9ce086460e8569a';
        $this->createPromotion(['id' => $promotionId, 'name' => 'MyPromo']);

        $ruleId = 'cb34dc6f20b6479aa975e1290f442e65';
        $this->createRule($ruleId);

        $progress = $this->import($context, PromotionDiscountDefinition::ENTITY_NAME, '/fixtures/promotion_discounts.csv', 'promotion_discounts.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        /** @var EntityRepository<PromotionCollection> $promotionRepo */
        $promotionRepo = static::getContainer()->get('promotion.repository');
        $promotion = $promotionRepo->search((new Criteria([$promotionId]))->addAssociation('discounts.discountRules'), $context)->getEntities()->first();
        static::assertNotNull($promotion);

        $discounts = $promotion->getDiscounts();
        static::assertInstanceOf(PromotionDiscountCollection::class, $discounts);
        static::assertCount(2, $discounts);

        $firstDiscount = $discounts->first();
        static::assertInstanceOf(PromotionDiscountEntity::class, $firstDiscount);
        static::assertSame('cart', $firstDiscount->getScope());
        static::assertSame('absolute', $firstDiscount->getType());
        static::assertSame(5.0, $firstDiscount->getValue());
        static::assertFalse($firstDiscount->isConsiderAdvancedRules());
        static::assertNull($firstDiscount->getMaxValue());
        static::assertSame('PRICE_ASC', $firstDiscount->getSorterKey());
        static::assertSame('ALL', $firstDiscount->getApplierKey());
        static::assertSame('ALL', $firstDiscount->getUsageKey());
        static::assertEmpty($firstDiscount->getPickerKey());
        static::assertInstanceOf(RuleCollection::class, $firstDiscountRules = $firstDiscount->getDiscountRules());
        static::assertEmpty($firstDiscountRules->getIds());

        $lastDiscount = $discounts->last();
        static::assertInstanceOf(PromotionDiscountEntity::class, $lastDiscount);
        static::assertSame('set', $lastDiscount->getScope());
        static::assertSame('percentage', $lastDiscount->getType());
        static::assertSame(2.5, $lastDiscount->getValue());
        static::assertTrue($lastDiscount->isConsiderAdvancedRules());
        static::assertSame(4.0, $lastDiscount->getMaxValue());
        static::assertSame('PRICE_DESC', $lastDiscount->getSorterKey());
        static::assertSame('1', $lastDiscount->getApplierKey());
        static::assertSame('1', $lastDiscount->getUsageKey());
        static::assertSame('VERTICAL', $lastDiscount->getPickerKey());
        static::assertInstanceOf(RuleCollection::class, $lastDiscountRules = $lastDiscount->getDiscountRules());
        static::assertContains($ruleId, $lastDiscountRules->getIds());

        $progress = $this->export($context, PromotionDiscountDefinition::ENTITY_NAME);
        static::assertImportExportSucceeded($progress);

        $csv = $this->getCsvContent($progress->getLogId());
        static::assertStringEqualsFile(__DIR__ . '/fixtures/promotion_discounts_export.csv', $csv);
    }

    public function testExportOrders(): void
    {
        $orderId = Uuid::randomHex();
        $testOrder = $this->getOrderData($orderId, Context::createDefaultContext())[0];
        $orderRepository = static::getContainer()->get('order.repository');

        $context = Context::createDefaultContext();
        $orderRepository->upsert([$testOrder], $context);

        $criteria = new Criteria([$testOrder['id']]);
        $progress = $this->export(Context::createDefaultContext(), OrderDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }

    public function testImportProductsWithUpdateByMapping(): void
    {
        $this->importCategoryCsv();
        $this->importPropertyCsv();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(ProductDefinition::ENTITY_NAME);
        $mappings = $clonedPropertyProfile->getMapping();
        $mappings[] = [
            'key' => 'unit.translations.DEFAULT.name',
            'mappedKey' => 'unit_name_en',
        ];
        $mappings[] = [
            'key' => 'unit.translations.DEFAULT.shortCode',
            'mappedKey' => 'unit_short_code_en',
        ];
        $mappings[] = [
            'key' => 'unit.translations.de-DE.name',
            'mappedKey' => 'unit_name_de',
        ];
        $mappings[] = [
            'key' => 'unit.translations.de-DE.shortCode',
            'mappedKey' => 'unit_short_code_de',
        ];
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);
        $updateBy = [
            ['entityName' => ProductDefinition::ENTITY_NAME, 'mappedKey' => 'productNumber'],
            ['entityName' => TaxDefinition::ENTITY_NAME, 'mappedKey' => 'taxRate'],
            ['entityName' => ProductManufacturerDefinition::ENTITY_NAME, 'mappedKey' => 'translations.DEFAULT.name'],
            ['entityName' => UnitDefinition::ENTITY_NAME, 'mappedKey' => 'translations.de-DE.name'],
            ['entityName' => CategoryDefinition::ENTITY_NAME, 'mappedKey' => 'translations.en-GB.name'],
            ['entityName' => PropertyGroupOptionDefinition::ENTITY_NAME, 'mappedKey' => 'translations.DEFAULT.name'],
        ];
        $this->updateProfileUpdateBy($clonedPropertyProfile->getId(), $updateBy);

        $progress = $this->import(
            $context,
            ProductDefinition::ENTITY_NAME,
            '/fixtures/products_with_update_by.csv',
            'products_with_update_by.csv',
            $clonedPropertyProfile->getId()
        );

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $products = $this->productRepository->search((new Criteria())->addAssociations(['categories', 'properties']), $context);

        static::assertCount(1, $products);
        static::assertInstanceOf(ProductEntity::class, $product = $products->first());
        static::assertInstanceOf(CategoryCollection::class, $categories = $product->getCategories());
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $properties = $product->getProperties());
        static::assertCount(3, $categories);
        static::assertCount(3, $properties);

        $taxes = static::getContainer()->get('tax.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('taxRate', 23)),
            $context
        )->getEntities();

        static::assertInstanceOf(TaxCollection::class, $taxes);
        static::assertCount(1, $taxes);
        static::assertInstanceOf(TaxEntity::class, $tax = $taxes->first());
        static::assertSame('changed', $tax->getName());

        $manufacturerCount = static::getContainer()->get('product_manufacturer.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'onlyone')),
            $context
        )->count();

        static::assertSame(1, $manufacturerCount);

        $units = static::getContainer()->get('unit.repository')->search(
            new Criteria(),
            $context
        )->getEntities();

        static::assertInstanceOf(UnitCollection::class, $units);
        static::assertCount(1, $units);
        static::assertInstanceOf(UnitEntity::class, $unit = $units->first());
        static::assertSame('foo', $unit->getName());
    }

    public function testImportProductsWithInvalidUpdateByMapping(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(ProductDefinition::ENTITY_NAME);
        $mappings = $clonedPropertyProfile->getMapping();
        $mappings[] = [
            'key' => 'manufacturer.link',
            'mappedKey' => 'manufacturer_link',
        ];
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);
        $updateBy = [
            ['entityName' => ProductManufacturerDefinition::ENTITY_NAME, 'mappedKey' => 'link'],
        ];
        $this->updateProfileUpdateBy($clonedPropertyProfile->getId(), $updateBy);

        $progress = $this->import(
            $context,
            ProductDefinition::ENTITY_NAME,
            '/fixtures/products_with_invalid_update_by.csv',
            'products_with_invalid_update_by.csv',
            $clonedPropertyProfile->getId()
        );

        static::assertImportExportFailed($progress);

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertGreaterThanOrEqual(1, \count($invalid));
        $first = $invalid[0];
        static::assertStringContainsString(
            (new UpdatedByValueNotFoundException(ProductManufacturerDefinition::ENTITY_NAME, 'link'))->getMessage(),
            $first['_error']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createCategoryProfileMock(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'technicalName' => 'test_profile',
            'label' => 'Test Profile',
            'sourceEntity' => 'category',
            'type' => ImportExportProfileEntity::TYPE_IMPORT_EXPORT,
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'config' => [],
            'mapping' => [
                ['key' => 'id', 'mappedKey' => 'id', 'position' => 1],
                ['key' => 'active', 'mappedKey' => 'active', 'position' => 2],
                ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name', 'position' => 3],
                ['key' => 'type', 'mappedKey' => 'type', 'position' => 0],
            ],
        ];
    }

    private function getCsvContent(string $logId): string
    {
        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        $logfile = $this->getLogEntity($logId)->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $logfile);

        $csv = $filesystem->read($logfile->getPath());
        static::assertIsString($csv);

        return $csv;
    }
}
