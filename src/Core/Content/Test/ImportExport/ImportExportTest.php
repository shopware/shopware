<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ImportExportTest extends TestCase
{
    use KernelTestBehaviour;
    use FilesystemBehaviour;
    use CacheTestBehaviour;
    use BasicTestDataBehaviour;
    use SessionTestBehaviour;
    use RequestStackTestBehaviour;

    use SalesChannelApiTestBehaviour;

    public const TEST_IMAGE = __DIR__ . '/fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');

        $connection = $this->getContainer()->get(Connection::class);

        // required for the testInvalidFile test
        $connection->setNestTransactionsWithSavepoints(true);

        $connection->beginTransaction();
    }

    public function tearDown(): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()
            ->get(Connection::class);

        static::assertEquals(
            1,
            $connection->getTransactionNestingLevel(),
            'Too many Nesting Levels.
            Probably one transaction was not closed properly.
            This may affect following Tests in an unpredictable manner!
            Current nesting level: "' . $connection->getTransactionNestingLevel() . '".'
        );

        $connection->rollBack();

        $connection->setNestTransactionsWithSavepoints(false);
    }

    public function testImportExport(): void
    {
        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        /** @var ImportExportService $importExportService */
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
        \file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));

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

        /** @var ImportExportService $importExportService */
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
        \file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));
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

        /** @var ImportExportService $importExportService */
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
        \file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));

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

        /** @var ImportExportService $importExportService */
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
        \file_put_contents($exportFileTmp, $filesystem->read($logEntity->getFile()->getPath()));

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
        static::assertCount(count($ids), $actual->getIds());

        /** @var EntityRepositoryInterface $optionRepository */
        $optionRepository = $this->getContainer()->get('property_group_option.repository');
        foreach ($groups as $group) {
            $ids = array_column($group['options'], 'id');
            $actual = $optionRepository->searchIds(new Criteria($ids), Context::createDefaultContext());
            static::assertCount(count($ids), $actual->getIds());
        }
    }

    public function importCategoryCsv(): void
    {
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        /** @var ImportExportService $importExportService */
        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(CategoryDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/categories.csv', 'categories.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), 5, 5);
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
    }

    public function importPropertyCsv(): void
    {
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        /** @var ImportExportService $importExportService */
        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/properties.csv', 'properties.csv', 'text/csv');
        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), 5, 5);
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
    }

    public function testProductsCsv(): void
    {
        $this->importCategoryCsv();
        $this->importPropertyCsv();

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        /** @var ImportExportService $importExportService */
        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/fixtures/products.csv', 'products.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            Context::createDefaultContext(),
            $profileId,
            $expireDate,
            $file
        );
        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $importExport = $factory->create($logEntity->getId(), 5, 5);
            $progress = $importExport->import(Context::createDefaultContext(), $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());
    }

    public function testInvalidFile(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `product`');

        $factory = $this->getContainer()->get(ImportExportFactory::class);

        /** @var ImportExportService $importExportService */
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

    public function testSalesChannelAssignment(): void
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
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/b',
            ]],
        ]);

        /** @var ImportExportService $importExportService */
        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(ProductDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $uploadedFile = new UploadedFile(__DIR__ . '/fixtures/products_with_visibilities.csv', 'products_with_visibilities.csv', 'text/csv');
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

    private function getDefaultProfileId(string $entity): string
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('systemDefault', true));
        $criteria->addFilter(new EqualsFilter('sourceEntity', $entity));

        return $profileRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
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
