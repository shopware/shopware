<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Pipe\PipeFactory;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Processing\Writer\CsvFileWriterFactory;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

class ImportExportTest extends TestCase
{
    use KernelTestBehaviour;
    use FilesystemBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use BasicTestDataBehaviour;
    use SessionTestBehaviour;
    use RequestStackTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SerializerCacheTestBehaviour;

    public const TEST_IMAGE = __DIR__ . '/fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var TraceableEventDispatcher
     */
    private $listener;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->listener = $this->getContainer()->get(EventDispatcherInterface::class);
    }

    public function testExportEvents(): void
    {
        $this->listener->addSubscriber(new StockSubscriber());

        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareExport(Context::createDefaultContext(), $profileId, $expireDate);

        $importExport = $factory->create($logEntity->getId());

        $productId = Uuid::randomHex();
        $product = $this->getTestProduct($productId);
        $newStock = (int) $product['stock'] + 1;

        $criteria = new Criteria([$productId]);
        $importExport->export(Context::createDefaultContext(), $criteria, 0);

        $events = array_column($this->listener->getCalledListeners(), 'event');
        static::assertContains(ImportExportBeforeExportRecordEvent::class, $events);

        $csv = $filesystem->read($logEntity->getFile()->getPath());
        static::assertStringContainsString(";{$newStock};", $csv);
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
        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareExport(Context::createDefaultContext(), $profileId, $expireDate);

        $importExport = $factory->create($logEntity->getId());

        $productId = Uuid::randomHex();
        $this->getTestProduct($productId);
        $criteria = new Criteria([$productId]);
        $progress = $importExport->export(Context::createDefaultContext(), $criteria, 0);

        static::assertTrue($progress->isFinished());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $importExport->export(Context::createDefaultContext(), $criteria);

        /** @var EntityRepositoryInterface $fileRepository */
        $fileRepository = $this->getContainer()->get('import_export_file.repository');
        /** @var ImportExportFileEntity|null $file */
        $file = $fileRepository->search(new Criteria([$logEntity->getFileId()]), Context::createDefaultContext())->first();

        static::assertNotNull($file);
        static::assertSame($filesystem->getSize($logEntity->getFile()->getPath()), $file->getSize());

        $this->productRepository->delete([['id' => $productId]], Context::createDefaultContext());
        $exportFileTmp = tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));

        $uploadedFile = new UploadedFile($exportFileTmp, 'test.csv', $logEntity->getProfile()->getFileType());

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $uploadedFile
        );

        $importExport = $factory->create($logEntity->getId());
        $progress = $importExport->import(Context::createDefaultContext());

        static::assertTrue($progress->isFinished());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('categories');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($product);
    }

    public function testCategory(): void
    {
        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');

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

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(CategoryDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareExport(Context::createDefaultContext(), $profileId, $expireDate);

        $importExport = $factory->create($logEntity->getId());

        $criteria = new Criteria([$rootId, $betweenId, $childId]);
        $progress = $importExport->export(Context::createDefaultContext(), $criteria, 0);
        static::assertTrue($progress->isFinished());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $importExport->export(Context::createDefaultContext(), $criteria);

        $categoryRepository->delete([['id' => $childId], ['id' => $betweenId], ['id' => $rootId]], Context::createDefaultContext());

        $exportFileTmp = tempnam(sys_get_temp_dir(), '');

        file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));
        $file = new UploadedFile($exportFileTmp, 'test.csv', $logEntity->getProfile()->getFileType());

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $file
        );

        $importExport = $factory->create($logEntity->getId());
        $progress = $importExport->import(Context::createDefaultContext());

        static::assertTrue($progress->isFinished());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $ids = $categoryRepository->searchIds(new Criteria([$rootId, $betweenId, $childId]), Context::createDefaultContext());
        static::assertCount(3, $ids->getIds());
        static::assertTrue($ids->has($rootId));
        static::assertTrue($ids->has($betweenId));
        static::assertTrue($ids->has($childId));
    }

    public function testNewsletterRecipient(): void
    {
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
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
            'salesChannelId' => Defaults::SALES_CHANNEL,
        ];
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('newsletter_recipient.repository');

        $context = Context::createDefaultContext();
        $repo->upsert([$testData], $context);

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(NewsletterRecipientDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareExport($context, $profileId, $expireDate);

        $importExport = $factory->create($logEntity->getId());

        $criteria = new Criteria([$testData['id']]);
        $progress = $importExport->export(Context::createDefaultContext(), $criteria, 0);
        static::assertTrue($progress->isFinished());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $repo->delete([['id' => $testData['id']]], Context::createDefaultContext());

        $exportFileTmp = tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));

        $uploadedFile = new UploadedFile($exportFileTmp, 'test.csv', $logEntity->getProfile()->getFileType());
        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $uploadedFile
        );

        $importExport = $factory->create($logEntity->getId());
        $progress = $importExport->import(Context::createDefaultContext());

        static::assertTrue($progress->isFinished());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $actualNewsletter = $repo->search(new Criteria([$testData['id']]), Context::createDefaultContext());
        static::assertNotNull($actualNewsletter);
    }

    public function testDefaultProperties(): void
    {
        static::markTestSkipped('Fix random failure');

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('property_group.repository');
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $groupCount = 10;
        $groupSize = 5;

        $total = $groupCount * $groupSize;

        $groups = [];
        for ($i = 0; $i < $groupCount; ++$i) {
            $data = [
                'id' => Uuid::randomHex(),
                'name' => 'Group ' . $i,
                'description' => 'Description ' . $i,
                'position' => $i + 1,
                'options' => [],
            ];

            for ($j = 0; $j < $groupSize; ++$j) {
                $data['options'][] = [
                    'id' => Uuid::randomHex(),
                    'name' => 'Option ' . $j . ' of group ' . $i,
                    'position' => $j,
                ];
            }

            $groups[] = $data;
        }

        $context = Context::createDefaultContext();
        $repository->upsert($groups, $context);

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareExport($context, $profileId, $expireDate);

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), $groupSize - 1, $groupSize - 1);
            $progress = $importExport->export(Context::createDefaultContext(), new Criteria(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame($total, $progress->getTotal());
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
        static::assertGreaterThan(0, $filesystem->getSize($logEntity->getFile()->getPath()));

        $exportFileTmp = tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $uploadedFile = new UploadedFile($exportFileTmp, 'test.csv', $logEntity->getProfile()->getFileType());

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $uploadedFile
        );

        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `property_group`');
        $connection->executeUpdate('DELETE FROM `property_group_option`');

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), $groupSize - 1, $groupSize - 1);
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        $ids = array_column($groups, 'id');
        $actual = $repository->searchIds(new Criteria($ids), Context::createDefaultContext());
        static::assertCount(\count($ids), $actual->getIds());

        /** @var EntityRepositoryInterface $optionRepository */
        $optionRepository = $this->getContainer()->get('property_group_option.repository');
        foreach ($groups as $group) {
            $ids = array_column($group['options'], 'id');
            $actual = $optionRepository->searchIds(new Criteria($ids), Context::createDefaultContext());
            static::assertCount(\count($ids), $actual->getIds());
        }
    }

    public function importCategoryCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(CategoryDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/categories.csv', 'categories.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
    }

    public function importPropertyCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/properties.csv', 'properties.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
    }

    public function importPropertyCsvWithoutIds(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/propertieswithoutid.csv', 'properties.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        /** @var EntityRepositoryInterface $propertyRepository */
        $propertyRepository = $this->getContainer()->get($logEntity->getProfile()->getSourceEntity() . '.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'alicebluenew'));
        $property = $propertyRepository->search($criteria, $context);
        static::assertCount(1, $property);
    }

    public function importPropertyWithDefaultsCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(PropertyGroupOptionDefinition::ENTITY_NAME);
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            if ($mappings[$key]['mappedKey'] === 'name') {
                $mappings[$key]['useDefaultValue'] = true;
                $mappings[$key]['defaultValue'] = 'MyDefaultNameForProperties';

                break;
            }
        }
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);

        // do the import
        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/properties_with_empty_names.csv', 'properties.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $clonedPropertyProfile->getId(),
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        // import should succeed even if required names are empty (they will be replaced by default values)
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        /** @var EntityRepositoryInterface $propertyRepository */
        $propertyRepository = $this->getContainer()->get($logEntity->getProfile()->getSourceEntity() . '.repository');

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
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(PropertyGroupOptionDefinition::ENTITY_NAME);
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            if ($mappings[$key]['mappedKey'] === 'media_url') {
                $mappings[$key]['requiredByUser'] = true;

                break;
            }
        }
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);

        // do the import
        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/properties.csv', 'properties.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $clonedPropertyProfile->getId(),
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        // import should fail even if all system required fields are set,
        // there are rows that have no values for user required fields.
        // Input CSV is the same as in the 'importPropertyCsv' test (which previously succeeded here).
        static::assertSame(Progress::STATE_FAILED, $progress->getState());
        static::assertSame(0, $progress->getProcessedRecords());

        // check the errors
        $config = Config::fromLog($importExport->getLogEntity()->getInvalidRecordsLog());
        $reader = new CsvReader();
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $resource = $filesystem->readStream($logEntity->getFile()->getPath() . '_invalid');
        $invalid = iterator_to_array($reader->read($config, $resource, 0));

        static::assertGreaterThanOrEqual(1, \count($invalid)); // there could already be other errors
        $first = $invalid[0];
        static::assertStringContainsString('media_url is set to required by the user but has no value', $first['_error']);
    }

    /**
     * @group slow
     */
    public function testProductsCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->importCategoryCsv();
        $this->importPropertyCsv();
        $this->importPropertyCsvWithoutIds();

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $this->importPropertyWithDefaultsCsv();
            $this->importPropertyWithUserRequiredCsv();
        }

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/products.csv', 'products.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
    }

    /**
     * @group slow
     */
    public function testProductsWithVariantsCsv(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_8097', $this);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `product`');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/products_with_variants.csv', 'products_with_variants.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);

        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
        static::assertEquals(2, $progress->getProcessedRecords());

        $productRepository = $this->getContainer()->get('product.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('options.group');
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('parentId', null)]));

        $result = $productRepository->search($criteria, Context::createDefaultContext());
        static::assertEquals(32, $result->count());
        static::assertCount(3, $result->first()->getVariation());
        static::assertContains('color', array_column($result->first()->getVariation(), 'group'));
        static::assertContains('size', array_column($result->first()->getVariation(), 'group'));
        static::assertContains('material', array_column($result->first()->getVariation(), 'group'));

        $criteria = new Criteria();
        $criteria->addAssociation('configuratorSettings');
        $criteria->addFilter(new EqualsFilter('parentId', null));

        $result = $productRepository->search($criteria, Context::createDefaultContext());
        static::assertEquals(10, $result->first()->getConfiguratorSettings()->count());
    }

    /**
     * @group slow
     */
    public function testProductsWithInvalidVariantsCsv(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped();
        }

        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `product`');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/products_with_invalid_variants.csv', 'products_with_invalid_variants.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);

        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_FAILED, $progress->getState());

        $config = Config::fromLog($importExport->getLogEntity()->getInvalidRecordsLog());
        $reader = new CsvReader();
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $resource = $filesystem->readStream($logEntity->getFile()->getPath() . '_invalid');
        $invalid = iterator_to_array($reader->read($config, $resource, 0));

        static::assertCount(2, $invalid);

        $first = $invalid[0];
        static::assertStringContainsString('size: M, L, XL, XXL | oops', $first['_error']);
        $second = $invalid[1];
        static::assertStringContainsString('size: , | color: Green, White, Black, Purple', $second['_error']);
    }

    public function testProductsWithOwnIdentifier(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $productIds = [
            Uuid::fromStringToHex('product1'),
            Uuid::fromStringToHex('product2'),
            Uuid::fromStringToHex('product3'),
            Uuid::fromStringToHex('product4'),
        ];

        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get(CategoryDefinition::ENTITY_NAME . '.repository');
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

        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $importExportService = $this->getContainer()->get(ImportExportService::class);
        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/products_with_own_identifier.csv', 'products.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');
        $count = $productRepository->search(new Criteria($productIds), $context)->count();
        static::assertSame(4, $count);

        $name = 'Name has changed';
        $productRepository->upsert([
            [
                'id' => $productIds[0],
                'name' => $name,
            ],
        ], $context);

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        /** @var ProductEntity $product */
        $criteria = new Criteria([$productIds[0]]);
        $criteria->addAssociation('categories');
        $product = $productRepository->search($criteria, $context)->first();

        static::assertNotSame($name, $product->getName());
        static::assertSame(Uuid::fromStringToHex('tax19'), $product->getTaxId());
        static::assertSame(Uuid::fromStringToHex('manufacturer1'), $product->getManufacturerId());
        static::assertSame(3, $product->getCategories()->count());
        static::assertSame($category1Id, $product->getCategories()->get($category1Id)->getId());
        static::assertSame($category2Id, $product->getCategories()->get($category2Id)->getId());
        static::assertSame($category3Id, $product->getCategories()->get($category3Id)->getId());
    }

    public function testProductsWithCategoryPaths(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get(CategoryDefinition::ENTITY_NAME . '.repository');
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

        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $importExportService = $this->getContainer()->get(ImportExportService::class);
        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/products_with_category_path.csv', 'products.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $criteria = new Criteria([Uuid::fromStringToHex('meinhappyproduct')]);
        $criteria->addAssociation('categories');

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get(ProductDefinition::ENTITY_NAME . '.repository');

        /** @var ProductEntity $product */
        $product = $productRepository->search($criteria, $context)->first();

        $categories = $product->getCategories()->getElements();
        static::assertSame(4, $product->getCategories()->count());
        static::assertArrayHasKey($categoryHome, $categories);
        static::assertArrayHasKey($categoryHomeFirstSecond, $categories);
        static::assertArrayHasKey($categoryHomeSecond, $categories);
        static::assertArrayHasKey($categoryHomeFirstNewSecondNew, $categories);

        $newCategoryLeaf = $product->getCategories()->get($categoryHomeFirstNewSecondNew);
        static::assertSame(Uuid::fromStringToHex('Main>First New'), $newCategoryLeaf->getParentId());
    }

    public function testInvalidFile(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `product`');

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $uploadedFile = new UploadedFile(__DIR__ . '/fixtures/products_with_invalid.csv', 'products_with_invalid.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $uploadedFile
        );
        $progress = $importExportService->getProgress($logEntity->getId(), 0);
        do {
            // simulate multiple requests
            $progress = $importExportService->getProgress($logEntity->getId(), $progress->getOffset());
            $importExport = $factory->create($logEntity->getId(), 2, 2);
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_FAILED, $progress->getState());

        $ids = $this->productRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(8, $ids->getIds());

        $config = Config::fromLog($importExport->getLogEntity()->getInvalidRecordsLog());
        $reader = new CsvReader();
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $resource = $filesystem->readStream($logEntity->getFile()->getPath() . '_invalid');
        $invalid = iterator_to_array($reader->read($config, $resource, 0));

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
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->createMock(EventDispatcherInterface::class),
            $this->getContainer()->get(Connection::class),
            $this->createMock(EntityRepositoryInterface::class),
            $pipe,
            $reader,
            $writer
        );

        $importExportService->method('getProgress')
            ->willReturnCallback(
                static function () use ($logEntity) {
                    return new Progress($logEntity->getId(), $logEntity->getState());
                }
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
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }

        $connection = $this->getContainer()->get(Connection::class);
        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $importExportService = $this->getContainer()->get(ImportExportService::class);
        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);
        $expireDate = new \DateTimeImmutable('2099-01-01');
        $uploadedFile = new UploadedFile(__DIR__ . '/fixtures/products_with_invalid.csv', 'products_with_invalid.csv', 'text/csv');

        $connection->rollBack();
        $connection->executeUpdate('DELETE FROM `product`');

        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $uploadedFile,
            [],
            true
        );
        static::assertSame(ImportExportLogEntity::ACTIVITY_DRYRUN, $logEntity->getActivity());

        $progress = $importExportService->getProgress($logEntity->getId(), 0);
        do {
            // simulate multiple requests
            $progress = $importExportService->getProgress($logEntity->getId(), $progress->getOffset());
            $importExport = $factory->create($logEntity->getId(), 2, 2);
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
        } while (!$progress->isFinished());
        static::assertSame(Progress::STATE_FAILED, $progress->getState());

        $ids = $this->productRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $ids->getIds());

        $importExport = $factory->create($logEntity->getId());
        $result = $importExport->getLogEntity()->getResult();
        static::assertEquals(2, $result['product_category']['insertError']);
        static::assertEquals(8, $result['product']['insert']);

        $connection->executeUpdate('DELETE FROM `import_export_log`');
        $connection->executeUpdate('DELETE FROM `import_export_file`');
        $connection->beginTransaction();
    }

    /**
     * @dataProvider salesChannelAssignementCsvProvider
     */
    public function testSalesChannelAssignment($csvPath): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `product`');
        $connection->executeUpdate('DELETE FROM `product_visibility`');

        $factory = $this->getContainer()->get(ImportExportFactory::class);

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

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $uploadedFile = new UploadedFile($csvPath, 'products_with_visibilities.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $uploadedFile
        );
        $progress = $importExportService->getProgress($logEntity->getId(), 0);
        do {
            // simulate multiple requests
            $progress = $importExportService->getProgress($logEntity->getId(), $progress->getOffset());
            $importExport = $factory->create($logEntity->getId(), 2, 2);
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $productRepository = $this->getContainer()->get('product.repository');
        $criteria = new Criteria([$productAId]);
        $criteria->addAssociation('visibilities');

        /** @var ProductEntity $productA */
        $productA = $productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($productA);

        static::assertCount(1, $productA->getVisibilities());
        static::assertNotNull($productA->getVisibilities()->filterBySalesChannelId($salesChannelAId)->first());

        $criteria = new Criteria([$productABId]);
        $criteria->addAssociation('visibilities');

        $productAB = $productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($productAB);

        static::assertCount(2, $productAB->getVisibilities());
        static::assertNotNull($productAB->getVisibilities()->filterBySalesChannelId($salesChannelAId)->first());
        static::assertNotNull($productAB->getVisibilities()->filterBySalesChannelId($salesChannelBId)->first());

        $criteria = new Criteria([$productCId]);
        $criteria->addAssociation('visibilities');

        $productC = $productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($productC);

        static::assertCount(0, $productC->getVisibilities());
        static::assertNull($productC->getVisibilities()->filterBySalesChannelId($salesChannelAId)->first());
        static::assertNull($productC->getVisibilities()->filterBySalesChannelId($salesChannelBId)->first());
    }

    /**
     * @dataProvider
     */
    public function salesChannelAssignementCsvProvider()
    {
        return [
            [__DIR__ . '/fixtures/products_with_visibilities.csv'],
            [__DIR__ . '/fixtures/products_with_visibility_names.csv'],
        ];
    }

    /**
     * @group slow
     */
    public function testCrossSellingCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);
        $expireDate = new \DateTimeImmutable('2099-01-01');

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $csvPath = __DIR__ . '/fixtures/cross_selling_products_with_own_identifier.csv';
        } else {
            $csvPath = __DIR__ . '/fixtures/cross_selling_products.csv';
        }

        $file = new UploadedFile($csvPath, 'products.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $profileId = $this->getDefaultProfileId(ProductCrossSellingDefinition::ENTITY_NAME);

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $csvPath = __DIR__ . '/fixtures/cross_selling_with_own_identifier.csv';
        } else {
            $csvPath = __DIR__ . '/fixtures/cross_selling.csv';
        }

        $file = new UploadedFile($csvPath, 'cross_selling.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $criteria = new Criteria(['cf682b73be1afad47d0f32559ac34627', 'c9a70321b66449abb54ba9306ad02835']);
        $criteria->addAssociation('crossSellings.assignedProducts');

        /** @var ProductEntity $productA */
        $productA = $this->productRepository->search($criteria, Context::createDefaultContext())->get('cf682b73be1afad47d0f32559ac34627');
        /** @var ProductEntity $productB */
        $productB = $this->productRepository->search($criteria, Context::createDefaultContext())->get('c9a70321b66449abb54ba9306ad02835');

        static::assertEquals('Lorem', $productA->getCrossSellings()->first()->getName());
        static::assertEquals(3, $productA->getCrossSellings()->first()->getAssignedProducts()->count());
        static::assertEquals('Ipsum', $productB->getCrossSellings()->first()->getName());
        static::assertEquals(3, $productB->getCrossSellings()->first()->getAssignedProducts()->count());

        $logEntity = $importExportService->prepareExport(Context::createDefaultContext(), $profileId, $expireDate);
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), 5, 5);
            $progress = $importExport->export(Context::createDefaultContext(), new Criteria(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $csv = $filesystem->read($logEntity->getFile()->getPath());

        static::assertStringContainsString(
            'f26b0d8f252a76f2f99337cced08314b|c1ace7586faa4342a4d3b33e6dd33b7c|c9a70321b66449abb54ba9306ad02835',
            $csv
        );

        static::assertStringContainsString(
            'c1ace7586faa4342a4d3b33e6dd33b7c|f26b0d8f252a76f2f99337cced08314b|cf682b73be1afad47d0f32559ac34627',
            $csv
        );
    }

    /**
     * @group slow
     */
    public function testCustomersCsv(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `customer`');

        $salesChannel = $this->createSalesChannel();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(CustomerDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/customers.csv', 'customers.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        $importExport = $factory->create($logEntity->getId(), 5, 5);
        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $criteria = new Criteria();
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');
        $repository = $this->getContainer()->get('customer.repository');
        $result = $repository->search($criteria, Context::createDefaultContext());

        static::assertSame(3, $result->count());

        /** @var CustomerEntity $customerWithMultipleAddresses */
        $customerWithMultipleAddresses = $result->get('0a1dea4bd2de43929ac210fd17339dde');

        static::assertSame(4, $customerWithMultipleAddresses->getAddresses()->count());
        static::assertSame('shopware AG', $customerWithMultipleAddresses->getDefaultBillingAddress()->getCompany());

        /** @var CustomerEntity $customerWithUpdatedAddresses */
        $customerWithUpdatedAddresses = $result->get('f3bb913bc8cc48479c3834a75e82920b');

        static::assertSame(2, $customerWithUpdatedAddresses->getAddresses()->count());
        static::assertSame('shopware AG', $customerWithUpdatedAddresses->getDefaultShippingAddress()->getCompany());

        $logEntity = $importExportService->prepareExport(Context::createDefaultContext(), $profileId, $expireDate);
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), 5, 5);
            $progress = $importExport->export(Context::createDefaultContext(), new Criteria(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $csv = $filesystem->read($logEntity->getFile()->getPath());

        static::assertStringContainsString($salesChannel['name'], $csv);
        static::assertStringContainsString('shopware AG', $csv);
        static::assertStringContainsString('en-GB', $csv);
        static::assertStringContainsString('Standard customer group', $csv);
    }

    public function testImportWithCreateAndUpdateConfig(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('FEATURE_NEXT_8097');
        }

        // expect default upsert
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => true,
            'updateEntities' => true,
        ]);
        static::assertEquals(5, $mockRepo->upsertCalls);
        static::assertEquals(0, $mockRepo->createCalls);
        static::assertEquals(0, $mockRepo->updateCalls);

        // expect create
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => true,
            'updateEntities' => false,
        ]);
        static::assertEquals(0, $mockRepo->upsertCalls);
        static::assertEquals(5, $mockRepo->createCalls);
        static::assertEquals(0, $mockRepo->updateCalls);

        // expect update
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => false,
            'updateEntities' => true,
        ]);
        static::assertEquals(0, $mockRepo->upsertCalls);
        static::assertEquals(0, $mockRepo->createCalls);
        static::assertEquals(5, $mockRepo->updateCalls);

        // expect upsert if both flags are false
        $mockRepo = $this->runCustomerImportWithConfigAndMockedRepository([
            'createEntities' => false,
            'updateEntities' => false,
        ]);
        static::assertEquals(5, $mockRepo->upsertCalls);
        static::assertEquals(0, $mockRepo->createCalls);
        static::assertEquals(0, $mockRepo->updateCalls);
    }

    private function runCustomerImportWithConfigAndMockedRepository(array $configOverrides): MockRepository
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $importExportService = $this->getContainer()->get(ImportExportService::class);
        $expireDate = new \DateTimeImmutable('2099-01-01');

        // setup profile
        $clonedCustomerProfile = $this->cloneDefaultProfile(CustomerDefinition::ENTITY_NAME);
        $config = array_merge($clonedCustomerProfile->getConfig(), $configOverrides);
        $this->updateProfileConfig($clonedCustomerProfile->getId(), $config);

        $file = new UploadedFile(__DIR__ . '/fixtures/customers.csv', 'customers_used_with_config.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            $context,
            $clonedCustomerProfile->getId(),
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);

        $pipeFactory = $this->getContainer()->get(PipeFactory::class);
        $readerFactory = $this->getContainer()->get(CsvReaderFactory::class);
        $writerFactory = $this->getContainer()->get(CsvFileWriterFactory::class);

        $mockRepository = new MockRepository($this->getContainer()->get(CustomerDefinition::class));

        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $mockRepository,
            $pipeFactory->create($logEntity),
            $readerFactory->create($logEntity),
            $writerFactory->create($logEntity),
            5,
            5
        );

        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), 'Import with MockRepository failed. Maybe check for mock errors.');

        return $mockRepository;
    }

    private function getDefaultProfileId(string $entity): string
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('systemDefault', true));
        $criteria->addFilter(new EqualsFilter('sourceEntity', $entity));

        return $profileRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    private function cloneDefaultProfile(string $entity): ImportExportProfileEntity
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $systemDefaultProfileId = $this->getDefaultProfileId($entity);
        $newId = Uuid::randomHex();
        $profileRepository->clone($systemDefaultProfileId, Context::createDefaultContext(), $newId);

        // get the cloned profile
        return $profileRepository->search(new Criteria([$newId]), Context::createDefaultContext())->first();
    }

    private function updateProfileMapping(string $profileId, array $mappings): void
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $profileRepository->update([
            [
                'id' => $profileId,
                'mapping' => $mappings,
            ],
        ], Context::createDefaultContext());
    }

    private function updateProfileConfig(string $profileId, array $config): void
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $profileRepository->update([
            [
                'id' => $profileId,
                'config' => $config,
            ],
        ], Context::createDefaultContext());
    }

    private function getTestProduct(string $id): array
    {
        $manufacturerId = Uuid::randomHex();
        $catId1 = Uuid::randomHex();
        $catId2 = Uuid::randomHex();
        $taxId = Uuid::randomHex();

        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');
        $manufacturerRepository->upsert([
            ['id' => $manufacturerId, 'name' => 'test'],
        ], Context::createDefaultContext());

        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');
        $categoryRepository->upsert([
            ['id' => $catId1, 'name' => 'test'],
            ['id' => $catId2, 'name' => 'bar'],
        ], Context::createDefaultContext());

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');
        $taxRepository->upsert([
            ['id' => $taxId, 'name' => 'test', 'taxRate' => 15],
        ], Context::createDefaultContext());

        $tempFile = tempnam(sys_get_temp_dir(), '');
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $mediaRepository = $this->getContainer()->get('media.repository');
        $mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );

        try {
            $this->getContainer()->get(FileSaver::class)->persistFileToMedia(
                $mediaFile,
                'test-file',
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $scA = $this->createSalesChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);
        $scB = $this->createSalesChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/b',
            ]],
        ]);
        $scC = $this->createSalesChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/c',
            ]],
        ]);

        $data = [
            'id' => $id,
            'versionId' => Defaults::LIVE_VERSION,
            'parentVersionId' => '0fa91ce3e96a4bc2be4bd9ce752c3425',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                Defaults::CURRENCY => [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 15,
                    'net' => 10,
                    'linked' => false,
                    'listPrice' => null,
                    'extensions' => [],
                ],
            ],
            'cover' => ['mediaId' => $mediaId],
            'manufacturerId' => $manufacturerId,
            'productManufacturerVersionId' => '0fa91ce3e96a4bc2be4bd9ce752c3425',
            'taxId' => $taxId,
            'categories' => [
                [
                    'id' => $catId1,
                ],
                [
                    'id' => $catId2,
                ],
            ],
            'active' => false,
            'isCloseout' => false,
            'markAsTopseller' => false,
            'maxPurchase' => 0,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'restockTime' => 3,
            'shippingFree' => false,
            'releaseDate' => $this->atomDate(),
            'createdAt' => $this->atomDate(),
            'translations' => [
                'en-GB' => [
                    'name' => 'Default name',
                ],
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'Default name',
                ],
                'de-DE' => [
                    'name' => 'German',
                    'description' => 'Beschreibung',
                ],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
                [
                    'salesChannelId' => $scA['id'],
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
                [
                    'salesChannelId' => $scB['id'],
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_SEARCH,
                ],
                [
                    'salesChannelId' => $scC['id'],
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_LINK,
                ],
            ],
        ];
        $this->productRepository->create([$data], Context::createDefaultContext());

        return $data;
    }

    private function atomDate($str = 'now'): \DateTimeInterface
    {
        return new \DateTimeImmutable((new \DateTimeImmutable($str))->format(\DateTime::ATOM));
    }
}

// phpcs:disable
class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'foo',
            ImportExportAfterImportRecordEvent::class => 'foo',
            ImportExportExceptionImportRecordEvent::class => 'foo',
        ];
    }

    public function foo(Event $event): void
    {
        //will be called on foo
    }
}

class StockSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeExportRecordEvent::class => 'onExport',
        ];
    }

    public function onExport(ImportExportBeforeExportRecordEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== 'product') {
            return;
        }

        $keys = $event->getConfig()->getMapping()->getKeys();
        if (!\in_array('stock', $keys, true)) {
            return;
        }

        $record = $event->getRecord();
        $record['stock'] = $record['stock'] + 1;
        $event->setRecord($record);
    }
}
// phpcs:enable

class MockRepository implements EntityRepositoryInterface
{
    public $createCalls = 0;

    public $updateCalls = 0;

    public $upsertCalls = 0;

    /**
     * @var EntityDefinition
     */
    private $definition;

    public function __construct(EntityDefinition $definition)
    {
        $this->definition = $definition;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        throw new \Error('MockRepository->aggregate: Not implemented');
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        throw new \Error('MockRepository->searchIds: Not implemented');
    }

    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        throw new \Error('MockRepository->clone: Not implemented');
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        throw new \Error('MockRepository->search: Not implemented');
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        ++$this->updateCalls;

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        ++$this->upsertCalls;

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        ++$this->createCalls;

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        throw new \Error('MockRepository->delete: Not implemented');
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        throw new \Error('MockRepository->createVersion: Not implemented');
    }

    public function merge(string $versionId, Context $context): void
    {
        throw new \Error('MockRepository->merge: Not implemented');
    }
}
