<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExportV2;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Job\File\ImportExportFile;
use Shopware\Core\Content\ImportExportV2\Job\Message\ProcessRunMessage;
use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Job\Request\ExportRunRequest;
use Shopware\Core\Content\ImportExportV2\Job\Request\ImportRunRequest;
use Shopware\Core\Content\ImportExportV2\Job\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Job\Service\RunService;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1774617000CreateImportExportV2Tables;

/**
 * @internal
 */
class RunServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private static bool $schemaInitialized = false;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $taxRepository;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $profileRepository;

    /**
     * @var EntityRepository<EntityCollection>
     */
    private EntityRepository $fileRepository;

    private RunService $runService;

    private Context $context;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureSchema();
    }

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->taxRepository = static::getContainer()->get('tax.repository');
        $this->profileRepository = static::getContainer()->get('import_export_v2_profile.repository');
        $this->fileRepository = static::getContainer()->get('import_export_v2_file.repository');
        $this->runService = static::getContainer()->get(RunService::class);

        $this->seedProfiles();
    }

    public function testStartImportUpdatesExistingProductUsingJsonProfile(): void
    {
        $originalTaxId = $this->createTax('Original tax', 19.0);
        $updatedTaxId = $this->createTax('Updated tax', 7.0);
        $productId = $this->createProduct('SW10001', 'Original product', $originalTaxId);

        $result = $this->runService->startImport(
            new ImportRunRequest(
                'product-json',
                new ImportExportFile(
                    'products.json',
                    'application/json',
                    (string) json_encode([[
                        'entity' => 'product',
                        'identifier' => ['productNumber' => 'SW10001'],
                        'payload' => [
                            'active' => true,
                            'stock' => 15,
                            'translations' => [
                                'DEFAULT' => [
                                    'name' => 'Updated product',
                                ],
                            ],
                            'tax' => [
                                'id' => $updatedTaxId,
                            ],
                        ],
                    ]], \JSON_THROW_ON_ERROR)
                )
            ),
            $this->context
        );

        static::assertSame(ImportExportV2RunEntity::STATE_QUEUED, $result->getState());
        static::assertSame(1, $this->getDispatchedMessageCount(ProcessRunMessage::class));

        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $persistedRun->getState());
        static::assertSame(1, $persistedRun->getProcessed());
        static::assertSame(1, $persistedRun->getSucceeded());
        static::assertSame(0, $persistedRun->getFailed());
        static::assertNotNull($this->runService->getFile((string) $persistedRun->getInputFileId(), $this->context));

        $product = $this->productRepository->search(new Criteria([$productId]), $this->context)->first();
        static::assertNotNull($product);
        static::assertSame(15, $product->jsonSerialize()['stock']);
        static::assertTrue($product->jsonSerialize()['active']);
        static::assertSame($updatedTaxId, $product->jsonSerialize()['taxId']);
        static::assertSame('Updated product', $product->jsonSerialize()['translated']['name']);
    }

    public function testStartExportBuildsImportExportJsonFile(): void
    {
        $taxId = $this->createTax('Export tax', 19.0);
        $productId = $this->createProduct('SW20001', 'Export product', $taxId, 42, true);

        $result = $this->runService->startExport(
            new ExportRunRequest('product-json', [$productId]),
            $this->context
        );

        static::assertSame(ImportExportV2RunEntity::STATE_QUEUED, $result->getState());
        static::assertSame(1, $this->getDispatchedMessageCount(ProcessRunMessage::class));

        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertNotNull($persistedRun->getOutputFileId());

        $file = $this->runService->getFile((string) $persistedRun->getOutputFileId(), $this->context);
        static::assertNotNull($file);

        $payload = json_decode($file->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $payload);
        static::assertSame('product', $payload[0]['entity']);
        static::assertSame('SW20001', $payload[0]['identifier']['productNumber']);
        static::assertTrue($payload[0]['payload']['active']);
        static::assertSame(42, $payload[0]['payload']['stock']);
        static::assertSame('Export product', $payload[0]['payload']['translations']['DEFAULT']['name']);
        static::assertSame($taxId, $payload[0]['payload']['tax']['id']);
    }

    public function testExportRunProcessesMultipleChunksAndKeepsFileValidJson(): void
    {
        $taxId = $this->createTax('Export tax', 19.0);
        $productOne = $this->createProduct('SW21001', 'Export one', $taxId, 11, true);
        $productTwo = $this->createProduct('SW21002', 'Export two', $taxId, 12, true);
        $productThree = $this->createProduct('SW21003', 'Export three', $taxId, 13, true);

        $result = $this->runService->startExport(
            new ExportRunRequest('product-json', [$productOne, $productTwo, $productThree], [], ['chunkSize' => 2]),
            $this->context
        );

        $this->clearQueue();
        $this->runService->process($result->getId(), $this->context);

        $runAfterFirstChunk = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($runAfterFirstChunk);
        static::assertSame(ImportExportV2RunEntity::STATE_QUEUED, $runAfterFirstChunk->getState());
        static::assertSame(2, $runAfterFirstChunk->getProcessed());

        $fileAfterFirstChunk = $this->runService->getFile((string) $runAfterFirstChunk->getOutputFileId(), $this->context);
        static::assertNotNull($fileAfterFirstChunk);

        $payloadAfterFirstChunk = json_decode($fileAfterFirstChunk->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $payloadAfterFirstChunk);

        $this->runWorker();

        $completedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($completedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $completedRun->getState());
        static::assertSame(3, $completedRun->getProcessed());

        $file = $this->runService->getFile((string) $completedRun->getOutputFileId(), $this->context);
        static::assertNotNull($file);

        $payload = json_decode($file->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(3, $payload);
    }

    public function testStartExportBuildsFilteredExportWithoutRecordIds(): void
    {
        $taxId = $this->createTax('Filtered export tax', 19.0);
        $includedOne = $this->createProduct('SW23001', 'Filtered one', $taxId, 5, true);
        $includedTwo = $this->createProduct('SW23002', 'Filtered two', $taxId, 6, true);
        $excluded = $this->createProduct('SW23003', 'Filtered out', $taxId, 7, false);

        $result = $this->runService->startExport(
            new ExportRunRequest('product-json', [], [
                [
                    'type' => 'equals',
                    'field' => 'active',
                    'value' => true,
                ],
            ], ['chunkSize' => 1]),
            $this->context
        );

        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $persistedRun->getState());
        static::assertSame(2, $persistedRun->getProcessed());
        static::assertSame(2, $persistedRun->getSucceeded());
        static::assertSame(2, $persistedRun->getTotalRecords());
        static::assertSame([
            [
                'type' => 'equals',
                'field' => 'active',
                'value' => true,
            ],
        ], $persistedRun->getExportFilters());

        $file = $this->runService->getFile((string) $persistedRun->getOutputFileId(), $this->context);
        static::assertNotNull($file);

        $payload = json_decode($file->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $payload);
        static::assertSameCanonicalizing(
            ['SW23001', 'SW23002'],
            array_column(array_column($payload, 'identifier'), 'productNumber')
        );
        static::assertNotContains('SW23003', array_column(array_column($payload, 'identifier'), 'productNumber'));

        static::assertNotNull($this->productRepository->search(new Criteria([$includedOne]), $this->context)->first());
        static::assertNotNull($this->productRepository->search(new Criteria([$includedTwo]), $this->context)->first());
        static::assertNotNull($this->productRepository->search(new Criteria([$excluded]), $this->context)->first());
    }

    public function testImportRunProcessesMultipleChunksAndRequeuesMessages(): void
    {
        $originalTaxId = $this->createTax('Original tax', 19.0);
        $updatedTaxId = $this->createTax('Updated tax', 7.0);

        $productOne = $this->createProduct('SW50001', 'Product one', $originalTaxId);
        $productTwo = $this->createProduct('SW50002', 'Product two', $originalTaxId);
        $productThree = $this->createProduct('SW50003', 'Product three', $originalTaxId);

        $result = $this->runService->startImport(
            new ImportRunRequest(
                'product-json',
                new ImportExportFile(
                    'products.json',
                    'application/json',
                    (string) json_encode([
                        $this->createImportRecord('SW50001', 'Updated one', $updatedTaxId, 11),
                        $this->createImportRecord('SW50002', 'Updated two', $updatedTaxId, 12),
                        $this->createImportRecord('SW50003', 'Updated three', $updatedTaxId, 13),
                    ], \JSON_THROW_ON_ERROR)
                ),
                ['chunkSize' => 2]
            ),
            $this->context
        );

        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $persistedRun->getState());
        static::assertSame(3, $persistedRun->getProcessed());
        static::assertSame(3, $persistedRun->getSucceeded());
        static::assertSame(3, $persistedRun->getTotalRecords());
        static::assertSame(3, $persistedRun->getOffset());
        static::assertGreaterThan(1, $this->getDispatchedMessageCount(ProcessRunMessage::class));

        static::assertSame(11, $this->loadProduct($productOne)->jsonSerialize()['stock']);
        static::assertSame(12, $this->loadProduct($productTwo)->jsonSerialize()['stock']);
        static::assertSame(13, $this->loadProduct($productThree)->jsonSerialize()['stock']);
    }

    public function testCancelStopsQueuedRunBeforeWorkerStarts(): void
    {
        $taxId = $this->createTax('Original tax', 19.0);
        $productId = $this->createProduct('SW60001', 'Original product', $taxId, 10, false);

        $result = $this->runService->startImport(
            new ImportRunRequest(
                'product-json',
                new ImportExportFile(
                    'products.json',
                    'application/json',
                    (string) json_encode([$this->createImportRecord('SW60001', 'Should stay original', $taxId, 99)], \JSON_THROW_ON_ERROR)
                )
            ),
            $this->context
        );

        $this->runService->cancel($result->getId(), $this->context);
        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_CANCELED, $persistedRun->getState());
        static::assertSame(0, $persistedRun->getProcessed());

        $product = $this->loadProduct($productId);
        static::assertSame(10, $product->jsonSerialize()['stock']);
        static::assertFalse($product->jsonSerialize()['active']);
    }

    public function testResumeContinuesFailedImportFromLastCommittedChunk(): void
    {
        $originalTaxId = $this->createTax('Original tax', 19.0);
        $updatedTaxId = $this->createTax('Updated tax', 7.0);

        $productOne = $this->createProduct('SW70001', 'Product one', $originalTaxId);
        $productTwo = $this->createProduct('SW70002', 'Product two', $originalTaxId);
        $productThree = $this->createProduct('SW70003', 'Product three', $originalTaxId);

        $result = $this->runService->startImport(
            new ImportRunRequest(
                'product-json',
                new ImportExportFile(
                    'products.json',
                    'application/json',
                    (string) json_encode([
                        $this->createImportRecord('SW70001', 'Updated one', $updatedTaxId, 21),
                        $this->createImportRecord('SW70002', 'Updated two', $updatedTaxId, 22),
                        $this->createImportRecord('SW70003', 'Updated three', $updatedTaxId, 23),
                    ], \JSON_THROW_ON_ERROR)
                ),
                ['chunkSize' => 2]
            ),
            $this->context
        );

        $file = $this->runService->getFile((string) $result->getInputFileId(), $this->context);
        static::assertNotNull($file);

        $this->clearQueue();
        $this->runService->process($result->getId(), $this->context);

        $afterFirstChunk = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($afterFirstChunk);
        static::assertSame(ImportExportV2RunEntity::STATE_QUEUED, $afterFirstChunk->getState());
        static::assertSame(2, $afterFirstChunk->getProcessed());
        static::assertSame(2, $afterFirstChunk->getOffset());

        $this->fileRepository->delete([['id' => $file->getId()]], $this->context);
        $this->runService->process($result->getId(), $this->context);

        $failedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($failedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_FAILED, $failedRun->getState());
        static::assertSame(2, $failedRun->getProcessed());
        static::assertSame(2, $failedRun->getOffset());
        static::assertNotNull($failedRun->getLastError());

        $this->fileRepository->upsert([[
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mimeType' => $file->getMimeType(),
            'contents' => $file->getContents(),
        ]], $this->context);

        $this->runService->resume($result->getId(), $this->context);
        $this->runWorker();

        $resumedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($resumedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $resumedRun->getState());
        static::assertSame(3, $resumedRun->getProcessed());
        static::assertSame(3, $resumedRun->getSucceeded());
        static::assertSame(3, $resumedRun->getOffset());

        static::assertSame(21, $this->loadProduct($productOne)->jsonSerialize()['stock']);
        static::assertSame(22, $this->loadProduct($productTwo)->jsonSerialize()['stock']);
        static::assertSame(23, $this->loadProduct($productThree)->jsonSerialize()['stock']);
    }

    public function testCsvFormatTranslatesRowsIntoImportExportRecordsAndBack(): void
    {
        $formatRegistry = static::getContainer()->get(FormatRegistry::class);
        $profile = $this->getProfile('product-csv');
        $format = $formatRegistry->get('csv');

        $rows = $format->getImportReader()->readChunk(new ImportExportFile(
            'products.csv',
            'text/csv',
            <<<CSV
product_number,active,stock,name,tax_id,visibility_sales_channel_id,visibility_level,category_ids
SW10001,1,15,Demo product,f1ce5e6a8f9b4d6f8a7b1c2d3e4f5a6b,0188da12724970b9b4a708298259b171,search,cat1|cat2
CSV
        ), $profile, 0, 10)['records'];

        static::assertCount(1, $rows);
        static::assertSame([
            'entity' => 'product',
            'identifier' => [
                'productNumber' => 'SW10001',
            ],
            'payload' => [
                'active' => true,
                'stock' => 15,
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'Demo product',
                    ],
                ],
                'tax' => [
                    'id' => 'f1ce5e6a8f9b4d6f8a7b1c2d3e4f5a6b',
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => '0188da12724970b9b4a708298259b171',
                        'visibility' => 'search',
                    ],
                ],
                'categories' => [
                    ['id' => 'cat1'],
                    ['id' => 'cat2'],
                ],
            ],
        ], $rows[0]);

        $writer = $format->getExportWriter();
        $file = new ImportExportFile('products.csv', 'text/csv', '');
        $writer->initialize($file, $profile);
        $writer->append($file, [
            new ImportExportRecord(
                'product',
                ['productNumber' => 'SW10001'],
                [
                    'active' => true,
                    'stock' => 15,
                    'translations' => ['DEFAULT' => ['name' => 'Demo product']],
                    'tax' => ['id' => 'f1ce5e6a8f9b4d6f8a7b1c2d3e4f5a6b'],
                    'visibilities' => [[
                        'salesChannelId' => '0188da12724970b9b4a708298259b171',
                        'visibility' => 'search',
                    ]],
                    'categories' => [
                        ['id' => 'cat1'],
                        ['id' => 'cat2'],
                    ],
                ]
            ),
        ], $profile);
        $writer->finalize($file, $profile);
        $contents = $file->getContents();

        $lines = preg_split('/\R/', trim($contents));
        \assert(\is_array($lines));
        static::assertCount(2, $lines);
        static::assertSame(
            ['product_number', 'active', 'stock', 'name', 'tax_id', 'visibility_sales_channel_id', 'visibility_level', 'category_ids'],
            str_getcsv($lines[0], escape: '\\')
        );
        static::assertSame(
            ['SW10001', '1', '15', 'Demo product', 'f1ce5e6a8f9b4d6f8a7b1c2d3e4f5a6b', '0188da12724970b9b4a708298259b171', 'search', 'cat1|cat2'],
            str_getcsv($lines[1], escape: '\\')
        );
    }

    public function testStartImportCollectsValidationFailures(): void
    {
        $taxId = $this->createTax('Original tax', 19.0);
        $productId = $this->createProduct('SW30001', 'Original product', $taxId, 10, false);

        $result = $this->runService->startImport(
            new ImportRunRequest(
                'product-json',
                new ImportExportFile(
                    'products.json',
                    'application/json',
                    (string) json_encode([[
                        'entity' => 'product',
                        'identifier' => ['productNumber' => 'SW30001'],
                        'payload' => [
                            'active' => true,
                            'stock' => 99,
                            'translations' => [
                                'DEFAULT' => [
                                    'name' => 'Should not be written',
                                ],
                            ],
                            'tax' => [
                                'id' => Uuid::randomHex(),
                            ],
                        ],
                    ]], \JSON_THROW_ON_ERROR)
                )
            ),
            $this->context
        );

        static::assertSame(ImportExportV2RunEntity::STATE_QUEUED, $result->getState());

        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $persistedRun->getState());
        static::assertSame(1, $persistedRun->getProcessed());
        static::assertSame(0, $persistedRun->getSucceeded());
        static::assertSame(1, $persistedRun->getFailed());
        static::assertCount(1, $persistedRun->getFailures());

        $product = $this->productRepository->search(new Criteria([$productId]), $this->context)->first();
        static::assertNotNull($product);
        static::assertSame(10, $product->jsonSerialize()['stock']);
        static::assertFalse($product->jsonSerialize()['active']);
        static::assertSame($taxId, $product->jsonSerialize()['taxId']);
    }

    public function testStartImportRejectsPayloadPathsOutsideTheSelectedProfile(): void
    {
        $taxId = $this->createTax('Original tax', 19.0);
        $productId = $this->createProduct('SW40001', 'Original product', $taxId, 10, false);

        $result = $this->runService->startImport(
            new ImportRunRequest(
                'product-json',
                new ImportExportFile(
                    'products.json',
                    'application/json',
                    (string) json_encode([[
                        'entity' => 'product',
                        'identifier' => ['productNumber' => 'SW40001'],
                        'payload' => [
                            'active' => true,
                            'stock' => 20,
                            'customFieldThatIsNotAllowed' => 'nope',
                        ],
                    ]], \JSON_THROW_ON_ERROR)
                )
            ),
            $this->context
        );

        static::assertSame(ImportExportV2RunEntity::STATE_QUEUED, $result->getState());

        $this->runWorker();

        $persistedRun = $this->runService->getRun($result->getId(), $this->context);
        static::assertNotNull($persistedRun);
        static::assertSame(ImportExportV2RunEntity::STATE_COMPLETED, $persistedRun->getState());
        static::assertSame(1, $persistedRun->getProcessed());
        static::assertSame(0, $persistedRun->getSucceeded());
        static::assertSame(1, $persistedRun->getFailed());
        static::assertStringContainsString('Payload path "customFieldThatIsNotAllowed" is not supported', $persistedRun->getFailures()[0]['message']);

        $product = $this->productRepository->search(new Criteria([$productId]), $this->context)->first();
        static::assertNotNull($product);
        static::assertSame(10, $product->jsonSerialize()['stock']);
        static::assertFalse($product->jsonSerialize()['active']);
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        (new Migration1774617000CreateImportExportV2Tables())->update($connection);

        self::$schemaInitialized = true;
    }

    private function seedProfiles(): void
    {
        $this->profileRepository->upsert([
            $this->createJsonProfilePayload($this->findExistingProfileId('product-json') ?? '11111111111111111111111111111111'),
            $this->createCsvProfilePayload($this->findExistingProfileId('product-csv') ?? '22222222222222222222222222222222'),
        ], $this->context);
    }

    /**
     * @return array<string, mixed>
     */
    private function createJsonProfilePayload(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'product-json',
            'entity' => 'product',
            'format' => 'json',
            'identifierPaths' => ['productNumber'],
            'payloadPaths' => ['active', 'stock', 'translations.DEFAULT.name', 'tax.id'],
            'relationModes' => ['tax' => 'replace'],
            'fieldMappings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCsvProfilePayload(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'product-csv',
            'entity' => 'product',
            'format' => 'csv',
            'identifierPaths' => ['productNumber'],
            'payloadPaths' => [
                'active',
                'stock',
                'translations.DEFAULT.name',
                'tax.id',
                'visibilities.0.salesChannelId',
                'visibilities.0.visibility',
                'categories.*.id',
            ],
            'relationModes' => [
                'tax' => 'replace',
                'visibilities' => 'replace',
                'categories' => 'replace',
            ],
            'fieldMappings' => [
                ['column' => 'product_number', 'path' => 'identifier.productNumber', 'separator' => null, 'type' => 'string'],
                ['column' => 'active', 'path' => 'payload.active', 'separator' => null, 'type' => 'bool'],
                ['column' => 'stock', 'path' => 'payload.stock', 'separator' => null, 'type' => 'int'],
                ['column' => 'name', 'path' => 'payload.translations.DEFAULT.name', 'separator' => null, 'type' => 'string'],
                ['column' => 'tax_id', 'path' => 'payload.tax.id', 'separator' => null, 'type' => 'string'],
                ['column' => 'visibility_sales_channel_id', 'path' => 'payload.visibilities.0.salesChannelId', 'separator' => null, 'type' => 'string'],
                ['column' => 'visibility_level', 'path' => 'payload.visibilities.0.visibility', 'separator' => null, 'type' => 'string'],
                ['column' => 'category_ids', 'path' => 'payload.categories.*.id', 'separator' => '|', 'type' => 'string'],
            ],
        ];
    }

    private function createTax(string $name, float $taxRate): string
    {
        $id = Uuid::randomHex();
        $this->taxRepository->create([[
            'id' => $id,
            'name' => $name,
            'taxRate' => $taxRate,
        ]], $this->context);

        return $id;
    }

    private function createProduct(
        string $productNumber,
        string $name,
        string $taxId,
        int $stock = 10,
        bool $active = true
    ): string {
        $id = Uuid::randomHex();

        $this->productRepository->create([[
            'id' => $id,
            'name' => $name,
            'productNumber' => $productNumber,
            'stock' => $stock,
            'active' => $active,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 15.0,
                'net' => 12.5,
                'linked' => false,
            ]],
        ]], $this->context);

        return $id;
    }

    private function getProfile(string $name): ImportExportV2ProfileEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));

        $profile = $this->profileRepository->search($criteria, $this->context)->first();
        static::assertInstanceOf(ImportExportV2ProfileEntity::class, $profile);

        return $profile;
    }

    private function findExistingProfileId(string $name): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));

        $profile = $this->profileRepository->search($criteria, $this->context)->first();

        return $profile instanceof ImportExportV2ProfileEntity ? $profile->getId() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createImportRecord(string $productNumber, string $name, string $taxId, int $stock): array
    {
        return [
            'entity' => 'product',
            'identifier' => ['productNumber' => $productNumber],
            'payload' => [
                'active' => true,
                'stock' => $stock,
                'translations' => [
                    'DEFAULT' => [
                        'name' => $name,
                    ],
                ],
                'tax' => [
                    'id' => $taxId,
                ],
            ],
        ];
    }

    private function loadProduct(string $productId): object
    {
        $product = $this->productRepository->search(new Criteria([$productId]), $this->context)->first();
        static::assertNotNull($product);

        return $product;
    }
}
