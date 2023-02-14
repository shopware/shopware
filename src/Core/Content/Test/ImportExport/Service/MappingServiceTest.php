<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Exception\FileEmptyException;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileContentException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Content\ImportExport\Service\MappingService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use const PHP_EOL;

/**
 * @internal
 */
#[Package('system-settings')]
class MappingServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MappingService $mappingService;

    private EntityRepository $profileRepository;

    private EntityRepository $fileRepository;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        $this->profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $this->fileRepository = $this->getContainer()->get('import_export_file.repository');
        $this->fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        $this->mappingService = new MappingService(
            $this->getContainer()->get(FileService::class),
            $this->profileRepository,
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );
    }

    /**
     * @dataProvider templateProfileProvider
     */
    public function testCreateTemplateFromProfileMapping($profile): void
    {
        if ($profile === null) {
            $profileId = Uuid::randomHex();
            self::expectExceptionObject(new EntityNotFoundException('import_export_profile', $profileId));
            $this->mappingService->createTemplate(Context::createDefaultContext(), $profileId);
        }

        $this->profileRepository->create([$profile], Context::createDefaultContext());

        if (empty($profile['mapping'])) {
            static::expectException(\RuntimeException::class);
        }

        $fileId = $this->mappingService->createTemplate(Context::createDefaultContext(), $profile['id']);

        if (empty($profile['mapping'])) {
            return;
        }

        static::assertNotEmpty($fileId);
        /** @var ImportExportFileEntity $file */
        $file = $this->fileRepository->search(new Criteria([$fileId]), Context::createDefaultContext())->first();
        static::assertNotEmpty($file);

        $csv = $this->fileSystem->read($file->getPath());

        foreach ($profile['mapping'] as $mapping) {
            static::assertStringContainsString(
                $mapping['mappedKey'],
                $csv,
                'Mapping mapped key should exists in CSV'
            );
        }
    }

    /**
     * @dataProvider mappingInputProvider
     */
    public function testGetMappingFromTemplate($data): void
    {
        // prepare profile for lookup
        $lookupMapping = [];
        foreach ($data['existingMappings'] ?? [] as $mappedKey => $key) {
            $lookupMapping[] = [
                'mappedKey' => $mappedKey,
                'key' => $key,
            ];
        }

        $this->profileRepository->create([
            [
                'name' => 'testProfileWithMapping',
                'fileType' => $data['fileType'] ?? 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
                'sourceEntity' => $data['sourceEntity'],
                'mapping' => $lookupMapping,
            ],
        ], Context::createDefaultContext());

        // prepare csv file for guessing
        $filePath = tempnam(sys_get_temp_dir(), '');
        if (!isset($data['emptyFile']) || $data['emptyFile'] === false) {
            $file = fopen($filePath, 'wb');
            static::assertIsResource($file);
            fwrite($file, (string) $data['csvHeader']);
            fclose($file);
        }
        $uploadedFile = new UploadedFile($filePath, 'test', $data['fileType'] ?? 'text/csv');

        try {
            $guessedMapping = $this->mappingService->getMappingFromTemplate(
                Context::createDefaultContext(),
                $uploadedFile,
                $data['sourceEntity']
            );
        } catch (\Throwable $exception) {
            if (!isset($data['expectedErrorClass'])) {
                throw $exception;
            }

            static::assertSame($data['expectedErrorClass'], $exception::class);

            return;
        }

        $testCase = 'test case data: ' . var_export($data, true);
        static::assertSame(is_countable($data['expectedMappings']) ? \count($data['expectedMappings']) : 0, $guessedMapping->count(), $testCase);

        foreach ($data['expectedMappings'] as $mappedKey => $key) {
            $mapping = $guessedMapping->getMapped($mappedKey);
            static::assertNotNull($mapping);

            static::assertSame($mappedKey, $mapping->getMappedKey(), $testCase);
            static::assertSame($key, $mapping->getKey(), $testCase);
        }

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function testSortingWorksAsExpected(): void
    {
        /** @var string $filePath */
        $filePath = tempnam(sys_get_temp_dir(), '');

        $csvHeaders = 'id;something;nothing;width;unit_id;unit_name';

        // creating a file
        /** @var resource $file */
        $file = fopen($filePath, 'wb');
        fwrite($file, $csvHeaders);
        fclose($file);

        // upload file
        $uploadedFile = new UploadedFile($filePath, 'test', 'text/csv');

        $guessedMapping = $this->mappingService->getMappingFromTemplate(
            Context::createDefaultContext(),
            $uploadedFile,
            'product'
        );

        $index = 0;
        foreach ($guessedMapping as $mapping) {
            static::assertSame($index, $mapping->getPosition());

            ++$index;
        }
    }

    public static function templateProfileProvider(): iterable
    {
        yield 'Import/Export profile with mapping' => [
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
        ];

        yield 'Export profile with mapping' => [
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
        ];

        yield 'Export profile with 3 mappings' => [
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
        ];

        yield 'Export profile with empty mapping' => [
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
        ];

        yield 'Export profile with null mapping' => [
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
        ];

        yield 'With invalid given profile' => [
            null,
        ];
    }

    public static function mappingInputProvider(): iterable
    {
        yield 'With existing mapping' => [
            [
                'sourceEntity' => 'product',
                'csvHeader' => 'id;something;nothing;width;unit_id;unit_name',
                'existingMappings' => [
                    'something' => 'manufacturer.id',
                ],
                'expectedMappings' => [
                    'id' => 'id',
                    'something' => 'manufacturer.id',
                    'nothing' => '',
                    'width' => 'width',
                    'unit_id' => 'unit.id',
                    'unit_name' => 'unit.translations.DEFAULT.name',
                ],
            ],
        ];

        yield 'Invalid file type' => [
            [
                'expectedErrorClass' => UnexpectedFileTypeException::class,
                'fileType' => 'text/json',
                'sourceEntity' => 'product',
                'csvHeader' => 'id;something;nothing;width;unit_id;unit_name',
            ],
        ];

        yield 'Empty file' => [
            [
                'emptyFile' => true,
                'expectedErrorClass' => FileEmptyException::class,
                'sourceEntity' => 'product',
                'csvHeader' => 'id;something;nothing;width;unit_id;unit_name',
            ],
        ];

        yield 'Invalid file content' => [
            [
                'expectedErrorClass' => InvalidFileContentException::class,
                'sourceEntity' => 'product',
                'csvHeader' => '' . PHP_EOL,
            ],
        ];

        yield 'Without existing mapping' => [
            [
                'sourceEntity' => 'product',
                'csvHeader' => 'id;parent_id;product_number;active;stock;name;description;price_net;price_gross;purchase_prices_net;purchase_prices_gross;tax_id;tax_rate;tax_name;cover_media_id;cover_media_url;cover_media_title;cover_media_alt;manufacturer_id;manufacturer_name;categories;sales_channel;propertyIds;optionIds',
                'existingMappings' => [],
                'expectedMappings' => [
                    'id' => 'id',
                    'parent_id' => 'parentId',
                    'product_number' => 'productNumber',
                    'active' => 'active',
                    'stock' => 'stock',
                    'name' => 'translations.DEFAULT.name',
                    'description' => 'translations.DEFAULT.description',
                    'price_net' => 'price.DEFAULT.net',
                    'price_gross' => 'price.DEFAULT.gross',
                    'purchase_prices_net' => 'purchasePrices.DEFAULT.net',
                    'purchase_prices_gross' => 'purchasePrices.DEFAULT.gross',
                    'tax_id' => 'tax.id',
                    'tax_rate' => 'tax.taxRate',
                    'tax_name' => 'tax.name',
                    'cover_media_id' => 'cover.media.id',
                    'cover_media_url' => 'cover.media.url',
                    'cover_media_title' => 'cover.media.translations.DEFAULT.title',
                    'cover_media_alt' => 'cover.media.translations.DEFAULT.alt',
                    'manufacturer_id' => 'manufacturer.id',
                    'manufacturer_name' => 'manufacturer.translations.DEFAULT.name',
                    'categories' => 'categories',
                    'sales_channel' => 'visibilities.all',
                    'propertyIds' => 'properties',
                    'optionIds' => 'options',
                ],
            ],
        ];
    }
}
