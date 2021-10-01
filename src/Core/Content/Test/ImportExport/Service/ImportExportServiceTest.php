<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Service;

use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Exception\ProfileWrongTypeException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportExportServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function mimeTypeProvider(): array
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

    /**
     * @dataProvider mimeTypeProvider
     */
    public function testMimeTypeValidation(string $clientMimeType, string $fileExtension, $expectedMimeType): void
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $importExportService = new ImportExportService(
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get('import_export_file.repository'),
            $this->getContainer()->get('import_export_log.repository'),
            $this->getContainer()->get('user.repository'),
            $profileRepository
        );

        $criteria = new Criteria();
        if (!Feature::isActive('FEATURE_NEXT_16119') || !Feature::isActive('FEATURE_NEXT_8097')) {
            $criteria->addFilter(new NotFilter('AND', [
                new EqualsFilter('sourceEntity', 'order'),
            ]));
        }

        if (Feature::isActive('FEATURE_NEXT_8097')) {
            $criteria->addFilter(new NotFilter('AND', [
                new EqualsFilter('type', 'export'),
            ]));
        }

        $profileId = $profileRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        static::assertNotnUll($profileId);

        $path = tempnam(sys_get_temp_dir(), '');

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
            $this->expectException(UnexpectedFileTypeException::class);
        }

        $importExportService->prepareImport(Context::createDefaultContext(), $profileId, new \DateTimeImmutable(), $uploadedFile);

        @unlink($path);
    }

    public function testConfig(): void
    {
        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $importExportService = new ImportExportService(
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get('import_export_file.repository'),
            $this->getContainer()->get('import_export_log.repository'),
            $this->getContainer()->get('user.repository'),
            $profileRepository
        );

        $baseConfig = [
            'includeVariants' => false,
        ];

        $profile = [
            'id' => Uuid::randomHex(),
            'name' => 'Test Profile',
            'label' => 'Test Profile',
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'config' => $baseConfig,
            'mapping' => [
                ['key' => 'foo', 'mappedKey' => 'bar'],
            ],
        ];
        $profileRepository->create([$profile], Context::createDefaultContext());

        $path = tempnam(sys_get_temp_dir(), '');
        copy(__DIR__ . '/../fixtures/categories.csv', $path);

        $uploadedFile = new UploadedFile($path, 'test', 'text/csv');
        $log = $importExportService->prepareImport(Context::createDefaultContext(), $profile['id'], new \DateTimeImmutable(), $uploadedFile);

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

        $log = $importExportService->prepareImport(Context::createDefaultContext(), $profile['id'], new \DateTimeImmutable(), $uploadedFile, $overrides);
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
     * @dataProvider profileProvider
     */
    public function testExportProfileShouldThrowExceptionInImport($profile, $task, $shouldThrowException): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            static::markTestSkipped('NEXT-8097');
        }

        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');

        $importExportService = new ImportExportService(
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get('import_export_file.repository'),
            $this->getContainer()->get('import_export_log.repository'),
            $this->getContainer()->get('user.repository'),
            $profileRepository
        );

        $profileRepository->create([$profile], Context::createDefaultContext());
        $path = tempnam(sys_get_temp_dir(), '');
        $uploadedFile = new UploadedFile($path, 'test', 'text/csv');

        if ($shouldThrowException) {
            static::expectException(ProfileWrongTypeException::class);
        }

        if ($task === 'import') {
            $importExportService->prepareImport(Context::createDefaultContext(), $profile['id'], new \DateTimeImmutable(), $uploadedFile);
        } else {
            $importExportService->prepareExport(Context::createDefaultContext(), $profile['id'], new \DateTimeImmutable());
        }
    }

    /**
     * @dataProvider templateProfileProvider
     */
    public function testCreateTemplateFromProfileMapping($profile): void
    {
        if (!Feature::isActive('FEATURE_NEXT_15998')) {
            static::markTestSkipped('NEXT-15998');
        }

        /** @var EntityRepositoryInterface $profileRepository */
        $profileRepository = $this->getContainer()->get('import_export_profile.repository');
        /** @var EntityRepositoryInterface $fileRepository */
        $fileRepository = $this->getContainer()->get('import_export_file.repository');
        /** @var FilesystemInterface $filesystem */
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $importExportService = new ImportExportService(
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get('import_export_file.repository'),
            $this->getContainer()->get('import_export_log.repository'),
            $this->getContainer()->get('user.repository'),
            $profileRepository
        );

        $profileRepository->create([$profile], Context::createDefaultContext());

        if (empty($profile['mapping'])) {
            static::expectException(\RuntimeException::class);
        }

        $fileId = $importExportService->createTemplate(Context::createDefaultContext(), $profile['id']);

        if (empty($profile['mapping'])) {
            return;
        }

        static::assertNotEmpty($fileId);
        /** @var ImportExportFileEntity $file */
        $file = $fileRepository->search(new Criteria([$fileId]), Context::createDefaultContext())->first();
        static::assertNotEmpty($file);

        $csv = $filesystem->read($file->getPath());

        foreach ($profile['mapping'] as $mapping) {
            static::assertStringContainsString(
                $mapping['mappedKey'],
                $csv,
                'Mapping mapped key should exists in CSV'
            );
        }
    }

    public function profileProvider(): array
    {
        return [
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
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
                'import',
                true,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
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
                'export',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
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
                'import',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
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
                'export',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
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
                'import',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
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
                'export',
                true,
            ],
        ];
    }

    public function templateProfileProvider(): array
    {
        return [
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_IMPORT_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'mappedKeyOne', 'mappedKey' => 'mapped_key_one'],
                    ],
                ],
                'import',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'mappedKeyOne', 'mappedKey' => 'mapped_key_one'],
                        ['key' => 'mappedKeyTwo', 'mappedKey' => 'mapped_key_two'],
                    ],
                ],
                'import',
                true,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [
                        ['key' => 'mappedKeyOne', 'mappedKey' => 'mapped_key_one'],
                        ['key' => 'mappedKeyTwo', 'mappedKey' => 'mapped_key_two'],
                        ['key' => 'mappedKeyThree', 'mappedKey' => 'mapped_key_three'],
                    ],
                ],
                'export',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => [],
                ],
                'export',
                false,
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test Profile',
                    'label' => 'Test Profile',
                    'sourceEntity' => 'product',
                    'type' => ImportExportProfileEntity::TYPE_EXPORT,
                    'fileType' => 'text/csv',
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'config' => [],
                    'mapping' => null,
                ],
                'export',
                false,
            ],
        ];
    }
}
