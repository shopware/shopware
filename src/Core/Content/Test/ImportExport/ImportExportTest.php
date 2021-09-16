<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ImportExportTest extends ImportExportTestCase
{
    use SalesChannelApiTestBehaviour;
    use OrderFixture;

    public function testExportEvents(): void
    {
        $this->listener->addSubscriber(new StockSubscriber());

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $productId = Uuid::randomHex();
        $product = $this->getTestProduct($productId);
        $newStock = (int) $product['stock'] + 1;

        $criteria = new Criteria([$productId]);
        $progress = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $criteria);

        $events = array_column($this->listener->getCalledListeners(), 'event');
        static::assertContains(ImportExportBeforeExportRecordEvent::class, $events);

        $csv = $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath());
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
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $productId = Uuid::randomHex();
        $this->getTestProduct($productId);
        $criteria = new Criteria([$productId]);
        $progress = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $progress = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, $criteria);

        /** @var EntityRepositoryInterface $fileRepository */
        $fileRepository = $this->getContainer()->get('import_export_file.repository');
        /** @var ImportExportFileEntity|null $file */
        $file = $fileRepository->search(new Criteria([$this->getLogEntity($progress->getLogId())->getFileId()]), Context::createDefaultContext())->first();

        static::assertNotNull($file);
        static::assertSame($filesystem->getSize($this->getLogEntity($progress->getLogId())->getFile()->getPath()), $file->getSize());

        $this->productRepository->delete([['id' => $productId]], Context::createDefaultContext());
        $exportFileTmp = tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath()));

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

        $criteria = new Criteria([$rootId, $betweenId, $childId]);
        $progress = $this->export(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $criteria);
        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $progress = $this->export(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $criteria);

        $categoryRepository->delete([['id' => $childId], ['id' => $betweenId], ['id' => $rootId]], Context::createDefaultContext());

        $exportFileTmp = tempnam(sys_get_temp_dir(), '');

        file_put_contents($exportFileTmp, $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath()));

        $this->import(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

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
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('newsletter_recipient.repository');

        $context = Context::createDefaultContext();
        $repo->upsert([$testData], $context);

        $criteria = new Criteria([$testData['id']]);
        $progress = $this->export($context, NewsletterRecipientDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $repo->delete([['id' => $testData['id']]], Context::createDefaultContext());

        $exportFileTmp = tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath()));

        $progress = $this->import($context, NewsletterRecipientDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

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

        $progress = $this->export($context, PropertyGroupOptionDefinition::ENTITY_NAME, null, $groupSize);

        static::assertSame($total, $progress->getTotal());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
        static::assertGreaterThan(0, $filesystem->getSize($this->getLogEntity($progress->getLogId())->getFile()->getPath()));

        $exportFileTmp = tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath()));

        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `property_group`');
        $connection->executeUpdate('DELETE FROM `property_group_option`');

        $this->import($context, PropertyGroupOptionDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

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

        /** @var EntityRepositoryInterface $propertyRepository */
        $propertyRepository = $this->getContainer()->get(PropertyGroupOptionDefinition::ENTITY_NAME . '.repository');

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
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
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

        /** @var EntityRepositoryInterface $propertyRepository */
        $propertyRepository = $this->getContainer()->get(PropertyGroupOptionDefinition::ENTITY_NAME . '.repository');

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
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
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

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria();
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('configuratorSettings');
        $criteria->addFilter(new EqualsFilter('parentId', 'e5c8b8f701034e8dbea72ac0fc32521e'));

        /** @var ProductEntity $result */
        $result = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertCount(2, $result->getVariation());

        $criteria->resetFilters();
        $criteria->addFilter(new EqualsFilter('id', 'e5c8b8f701034e8dbea72ac0fc32521e'));

        /** @var ProductEntity $result */
        $result = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals(2, $result->getConfiguratorSettings()->count());
    }

    public function testProductsCoverIsUpdated(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_updated_cover.csv', 'products.csv');

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(
            (new Criteria(['e5c8b8f701034e8dbea72ac0fc32521e']))->addAssociation('media'),
            Context::createDefaultContext()
        )->first();

        static::assertEquals(1, $product->getMedia()->count());
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

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_variants.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
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

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_own_identifier.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

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

        $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_own_identifier.csv', 'products.csv');

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

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, '/fixtures/products_with_category_path.csv', 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

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

        $connection->rollBack();
        $connection->executeUpdate('DELETE FROM `product`');

        $progress = $this->import(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME, '/fixtures/products_with_invalid_dryrun.csv', 'products.csv', null, true);
        static::assertImportExportFailed($progress);

        $ids = $this->productRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $ids->getIds());

        $result = $this->getLogEntity($progress->getLogId())->getResult();
        static::assertEquals(2, $result['product_category']['insertError']);
        static::assertEquals(8, $result['product']['insert']);

        $connection->executeUpdate('DELETE FROM `import_export_log`');
        $connection->executeUpdate('DELETE FROM `import_export_file`');
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

        static::assertSame(2, $result->count());
        $products = $result->getElements();

        static::assertSame(100.0, $products['bf44b430d7cd47fcac93310edf4fe4e1']->getPrice()->first()->getListPrice()->getNet());
        static::assertSame(5000.0, $products['bf44b430d7cd47fcac93310edf4fe4e1']->getPrice()->first()->getListPrice()->getGross());
        static::assertFalse($products['bf44b430d7cd47fcac93310edf4fe4e1']->getPrice()->first()->getListPrice()->getLinked());

        static::assertSame(20.0, $products['bf44b430d7cd47fcac93310edf4fe4e2']->getPrice()->first()->getListPrice()->getNet());
        static::assertSame(50.0, $products['bf44b430d7cd47fcac93310edf4fe4e2']->getPrice()->first()->getListPrice()->getGross());
        static::assertTrue($products['bf44b430d7cd47fcac93310edf4fe4e2']->getPrice()->first()->getListPrice()->getLinked());
    }

    /**
     * @dataProvider salesChannelAssignementCsvProvider
     */
    public function testSalesChannelAssignment($csvPath): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `product`');
        $connection->executeUpdate('DELETE FROM `product_visibility`');

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
            ['/fixtures/products_with_visibilities.csv'],
            ['/fixtures/products_with_visibility_names.csv'],
        ];
    }

    /**
     * @group slow
     */
    public function testCrossSellingCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $csvPath = '/fixtures/cross_selling_products_with_own_identifier.csv';
        } else {
            $csvPath = '/fixtures/cross_selling_products.csv';
        }

        $progress = $this->import($context, ProductDefinition::ENTITY_NAME, $csvPath, 'products.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $csvPath = '/fixtures/cross_selling_with_own_identifier.csv';
        } else {
            $csvPath = '/fixtures/cross_selling.csv';
        }

        $progress = $this->import($context, ProductCrossSellingDefinition::ENTITY_NAME, $csvPath, 'cross_selling.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

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

        $progress = $this->export($context, ProductCrossSellingDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $csv = $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath());

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

        $progress = $this->import($context, CustomerDefinition::ENTITY_NAME, '/fixtures/customers.csv', 'customers.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

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

        $progress = $this->export($context, CustomerDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $csv = $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath());

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

    public function testPromotionCodeImportExport(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM `promotion_individual_code`');

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
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $repository = $this->getContainer()->get('promotion_individual_code.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('promotion');
        $result = $repository->search($criteria, Context::createDefaultContext());

        static::assertSame(13, $result->count());

        /** @var PromotionIndividualCodeEntity $promoCodeResult */
        foreach ($result as $promoCodeResult) {
            static::assertTrue($promoCodeResult->getPromotion()->isUseIndividualCodes(), 'Promotion should have useIndividualCodes set to true after import');
        }

        // export
        $progress = $this->export($context, PromotionIndividualCodeDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $csv = $filesystem->read($this->getLogEntity($progress->getLogId())->getFile()->getPath());

        // validate export
        /** @var PromotionIndividualCodeEntity $promoCodeResult */
        foreach ($result as $promoCodeResult) {
            static::assertStringContainsString($promoCodeResult->getId(), $csv);
            static::assertStringContainsString($promoCodeResult->getPromotion()->getId(), $csv);
            static::assertStringContainsString($promoCodeResult->getPromotion()->getName(), $csv);
            static::assertStringContainsString($promoCodeResult->getCode(), $csv);
        }
    }

    public function testExportOrders(): void
    {
        $orderId = Uuid::randomHex();
        $testOrder = $this->getOrderData($orderId, Context::createDefaultContext())[0];
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        $context = Context::createDefaultContext();
        $orderRepository->upsert([$testOrder], $context);

        $criteria = new Criteria([$testOrder['id']]);
        $progress = $this->export(Context::createDefaultContext(), OrderDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }
}
