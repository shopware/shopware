<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ImportExportService::class)]
class ImportExportServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const TEST_PROFILE_NAME = 'Test Profile';

    /**
     * @var EntityRepository<EntityCollection<ImportExportProfileEntity>>
     */
    private EntityRepository $profileRepository;

    private ImportExportService $importExportService;

    protected function setUp(): void
    {
        $this->profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $this->importExportService = new ImportExportService(
            $this->getContainer()->get('import_export_log.repository'),
            $this->getContainer()->get('user.repository'),
            $this->profileRepository,
            $this->getContainer()->get(FileService::class)
        );
    }

    /**
     * @return list<array{clientMimeType: string, fileExtension: string, expectedMimeType: string|false}>
     */
    public static function mimeTypeProvider(): array
    {
        return [
            [
                'clientMimeType' => 'text/csv',
                'fileExtension' => 'csv',
                'expectedMimeType' => 'text/csv',
            ],
            [
                'clientMimeType' => 'text/x-csv',
                'fileExtension' => 'csv',
                'expectedMimeType' => 'text/csv',
            ],
            [
                'clientMimeType' => 'application/vnd.ms-excel',
                'fileExtension' => 'csv',
                'expectedMimeType' => 'text/csv',
            ],

            [
                'clientMimeType' => 'text/csv',
                'fileExtension' => '',
                'expectedMimeType' => 'text/csv',
            ],
            [
                'clientMimeType' => 'text/x-csv',
                'fileExtension' => '',
                'expectedMimeType' => 'text/csv',
            ],
            [
                'clientMimeType' => 'text/csv',
                'fileExtension' => 'txt',
                'expectedMimeType' => 'text/csv',
            ],
            [
                'clientMimeType' => 'text/x-csv',
                'fileExtension' => 'txt',
                'expectedMimeType' => 'text/csv',
            ],

            [
                'clientMimeType' => 'application/octet-stream',
                'fileExtension' => 'csv',
                'expectedMimeType' => 'text/csv',
            ],
            [
                'clientMimeType' => 'text/xml',
                'fileExtension' => 'xml',
                'expectedMimeType' => false,
            ],
            [
                'clientMimeType' => 'text/xml',
                'fileExtension' => '',
                'expectedMimeType' => false,
            ],
            [
                'clientMimeType' => 'application/xml',
                'fileExtension' => 'xml',
                'expectedMimeType' => false,
            ],
            [
                'clientMimeType' => 'application/xml',
                'fileExtension' => '',
                'expectedMimeType' => false,
            ],
            [
                'clientMimeType' => 'application/vnd.ms-excel',
                'fileExtension' => 'xls',
                'expectedMimeType' => false,
            ],
        ];
    }

    #[DataProvider('mimeTypeProvider')]
    public function testMimeTypeValidation(string $clientMimeType, string $fileExtension, string|false $expectedMimeType): void
    {
        $criteria = new Criteria();

        $criteria->addFilter(new NotFilter('AND', [
            new EqualsFilter('type', ImportExportProfileEntity::TYPE_EXPORT),
        ]));

        $profileId = $this->profileRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        static::assertNotNull($profileId);

        $path = tempnam(sys_get_temp_dir(), '');

        static::assertIsString($path);
        copy(__DIR__ . '/../fixtures/categories.csv', $path);

        $name = 'test';
        if ($fileExtension) {
            $name .= '.' . $fileExtension;
        }

        $uploadedFile = new UploadedFile(
            $path,
            $name,
            $clientMimeType
        );

        if ($expectedMimeType === false) {
            static::expectExceptionObject(ImportExportException::unexpectedFileType($clientMimeType, 'text/csv'));
        }

        $this->importExportService->prepareImport(Context::createDefaultContext(), $profileId, new \DateTimeImmutable(), $uploadedFile);

        @unlink($path);
    }

    public function testConfig(): void
    {
        $baseConfig = [
            'includeVariants' => false,
        ];

        $profile = [
            'id' => Uuid::randomHex(),
            'name' => self::TEST_PROFILE_NAME,
            'label' => self::TEST_PROFILE_NAME,
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'config' => $baseConfig,
            'mapping' => [
                ['key' => 'foo', 'mappedKey' => 'bar'],
            ],
        ];
        $this->profileRepository->create([$profile], Context::createDefaultContext());

        $path = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($path);

        copy(__DIR__ . '/../fixtures/categories.csv', $path);

        $uploadedFile = new UploadedFile($path, 'test', 'text/csv');
        $log = $this->importExportService->prepareImport(Context::createDefaultContext(), $profile['id'], new \DateTimeImmutable(), $uploadedFile);

        $actualConfig = Config::fromLog($log);

        static::assertFalse($actualConfig->get('includeVariants'));
        static::assertSame($profile['delimiter'], $actualConfig->get('delimiter'));
        static::assertSame($profile['enclosure'], $actualConfig->get('enclosure'));
        static::assertSame($profile['sourceEntity'], $actualConfig->get('sourceEntity'));
        static::assertSame($profile['fileType'], $actualConfig->get('fileType'));
        static::assertSame($profile['name'], $actualConfig->get('profileName'));

        $expectedMapping = MappingCollection::fromIterable($profile['mapping']);
        static::assertEquals($expectedMapping, $actualConfig->getMapping());

        $overrides = [
            'parameters' => [
                'includeVariants' => true,
                'fooBar' => 'baz',
                'enclosure' => '\'',
            ],
            'mapping' => [
                ['key' => 'zxcv', 'mappedKey' => 'qwer'],
            ],
        ];

        $log = $this->importExportService->prepareImport(Context::createDefaultContext(), $profile['id'], new \DateTimeImmutable(), $uploadedFile, $overrides);
        $actualConfig = Config::fromLog($log);

        static::assertTrue($actualConfig->get('includeVariants'));
        static::assertSame($overrides['parameters']['fooBar'], $actualConfig->get('fooBar'));
        static::assertSame($overrides['parameters']['enclosure'], $actualConfig->get('enclosure'));
        static::assertSame($profile['delimiter'], $actualConfig->get('delimiter'));
        static::assertSame($profile['sourceEntity'], $actualConfig->get('sourceEntity'));
        static::assertSame($profile['fileType'], $actualConfig->get('fileType'));

        $expectedMapping = MappingCollection::fromIterable($overrides['mapping']);
        static::assertEquals($expectedMapping, $actualConfig->getMapping());
    }

    /**
     * @param array<string, mixed>                                                          $profile
     * @param ImportExportProfileEntity::TYPE_EXPORT|ImportExportProfileEntity::TYPE_IMPORT $task
     * @param ImportExportLogEntity::ACTIVITY_*|null                                        $activity
     */
    #[DataProvider('profileProvider')]
    public function testPrepareImportAndPrepareExportWithVariousProfileTypesAndActivities(array $profile, string $task, bool $shouldThrowException, ?string $activity = null): void
    {
        $context = Context::createDefaultContext();

        $this->profileRepository->create([$profile], $context);

        if ($shouldThrowException) {
            $this->expectException(ImportExportException::profileWrongType($profile['id'], $profile['type'])::class);
        }

        if ($task === ImportExportProfileEntity::TYPE_IMPORT) {
            $path = tempnam(sys_get_temp_dir(), '');
            static::assertIsString($path);
            $uploadedFile = new UploadedFile($path, 'test', 'text/csv');
            $logEntity = $this->importExportService->prepareImport($context, $profile['id'], new \DateTimeImmutable(), $uploadedFile);
        } else {
            $activity = $activity ?? ImportExportLogEntity::ACTIVITY_EXPORT;
            $logEntity = $this->importExportService->prepareExport($context, $profile['id'], new \DateTimeImmutable(), activity: $activity);
        }

        static::assertSame($profile['id'], $logEntity->getProfileId());
        static::assertSame(self::TEST_PROFILE_NAME, $logEntity->getProfileName());
    }

    /**
     * @return array<array{0: array<string, mixed>, 1: ImportExportProfileEntity::TYPE_EXPORT|ImportExportProfileEntity::TYPE_IMPORT, 2: bool, 3?: ImportExportLogEntity::ACTIVITY_*|null}>
     */
    public static function profileProvider(): array
    {
        return [
            'Import with export type should throw exception' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_IMPORT,
                true,
            ],
            'Export with export type should not throw exception' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_EXPORT,
                false,
            ],
            'Export with import type should not throw exception if invalid records should be exported' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_IMPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_EXPORT,
                false,
                ImportExportLogEntity::ACTIVITY_INVALID_RECORDS_EXPORT,
            ],
            'Import with import-export type should not throw exception' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_IMPORT_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_IMPORT,
                false,
            ],
            'Export with import-export type should not throw exception' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_IMPORT_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_EXPORT,
                false,
            ],
            'Import with import type should not throw exception' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_IMPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_IMPORT,
                false,
            ],
            'Export with import type should throw exception' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => self::TEST_PROFILE_NAME,
                    'label' => self::TEST_PROFILE_NAME,
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_IMPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'foo', 'mappedKey' => 'bar'],
                    ],
                ],
                ImportExportProfileEntity::TYPE_EXPORT,
                true,
            ],
        ];
    }
}
