<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Pipe\PipeFactory;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\CsvFileWriterFactory;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

abstract class ImportExportTestCase extends TestCase
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
    protected $productRepository;

    /**
     * @var TraceableEventDispatcher
     */
    protected $listener;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->listener = $this->getContainer()->get(EventDispatcherInterface::class);
    }

    public static function assertImportExportSucceeded(Progress $progress, array $invalidLog = []): void
    {
        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), json_encode($invalidLog));
    }

    public static function assertImportExportFailed(Progress $progress): void
    {
        static::assertSame(Progress::STATE_FAILED, $progress->getState());
    }

    protected function runCustomerImportWithConfigAndMockedRepository(array $configOverrides): MockRepository
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

    protected function createPromotion(array $promotionOverride = []): array
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $this->getContainer()->get('promotion.repository');

        $promotion = array_merge([
            'id' => $promotionOverride['id'] ?? Uuid::randomHex(),
            'name' => 'Test case promotion',
            'active' => true,
            'useIndividualCodes' => true,
        ], $promotionOverride);

        $promotionRepository->upsert([$promotion], Context::createDefaultContext());

        return $promotion;
    }

    protected function createPromotionCode(string $promotionId, array $promotionCodeOverride = []): array
    {
        /** @var EntityRepositoryInterface $promotionCodeRepository */
        $promotionCodeRepository = $this->getContainer()->get('promotion_individual_code.repository');

        $promotionCode = array_merge([
            'id' => $promotionCodeOverride['id'] ?? Uuid::randomHex(),
            'promotionId' => $promotionId,
            'code' => 'TestCode',
        ], $promotionCodeOverride);

        $promotionCodeRepository->upsert([$promotionCode], Context::createDefaultContext());

        return $promotionCode;
    }

    protected function getDefaultProfileId(string $entity): string
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('systemDefault', true));
        $criteria->addFilter(new EqualsFilter('sourceEntity', $entity));

        return $profileRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    protected function cloneDefaultProfile(string $entity): ImportExportProfileEntity
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $systemDefaultProfileId = $this->getDefaultProfileId($entity);
        $newId = Uuid::randomHex();
        $profileRepository->clone($systemDefaultProfileId, Context::createDefaultContext(), $newId);

        // get the cloned profile
        return $profileRepository->search(new Criteria([$newId]), Context::createDefaultContext())->first();
    }

    protected function updateProfileMapping(string $profileId, array $mappings): void
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

    protected function updateProfileConfig(string $profileId, array $config): void
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

    protected function getTestProduct(string $id): array
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
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
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

    protected function atomDate($str = 'now'): \DateTimeInterface
    {
        return new \DateTimeImmutable((new \DateTimeImmutable($str))->format(\DateTime::ATOM));
    }

    protected function import(Context $context, string $entityName, string $path, string $originalName, ?string $profileId = null, bool $dryrun = false, bool $absolutePath = false): Progress
    {
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $profileId ?? $this->getDefaultProfileId($entityName);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile((!$absolutePath ? __DIR__ : '') . $path, $originalName, 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file,
            [],
            $dryrun
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $progress = $importExportService->getProgress($logEntity->getId(), $progress->getOffset());
            $importExport = $factory->create($logEntity->getId(), 5, 5);
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        return $progress;
    }

    protected function export(Context $context, string $entityName, ?Criteria $criteria = null, ?int $groupSize = null): Progress
    {
        $factory = $this->getContainer()->get(ImportExportFactory::class);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId($entityName);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $logEntity = $importExportService->prepareExport($context, $profileId, $expireDate);

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);
        do {
            $groupSize = $groupSize ? $groupSize - 1 : 0;
            $criteria = $criteria ?? new Criteria();
            $importExport = $factory->create($logEntity->getId(), $groupSize, $groupSize);
            $progress = $importExport->export(Context::createDefaultContext(), $criteria, $progress->getOffset());
        } while (!$progress->isFinished());

        return $progress;
    }

    protected function getLogEntity(string $logId): ImportExportLogEntity
    {
        return $this->getContainer()
            ->get('import_export_log.repository')
            ->search((new Criteria([$logId]))->addAssociation('profile'), Context::createDefaultContext())
            ->first();
    }

    protected function getInvalidLogContent(?string $invalidLogId): array
    {
        if (!$invalidLogId) {
            return [];
        }

        $logEntity = $this->getLogEntity($invalidLogId);
        $config = Config::fromLog($logEntity);
        $reader = new CsvReader();
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $resource = $filesystem->readStream($logEntity->getFile()->getPath());
        $invalid = iterator_to_array($reader->read($config, $resource, 0));

        return $invalid;
    }
}
