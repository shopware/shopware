<?php declare(strict_types=1);

namespace Scripts\Examples;

use Shopware\Core\Content\ImportExportV2\File\FileService;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Service\RunService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

require_once __DIR__ . '/base-script.php';
require_once __DIR__ . '/import-export-v2-product-profiles.php';

$env = 'prod'; // by default, kernel gets booted in dev

$kernel = require __DIR__ . '/../boot/boot.php';

class ImportExportV2ProductImport extends BaseScript
{
    public function run(): void
    {
        $context = Context::createCLIContext();

        $profiles = buildProductImportExportV2Profiles();

        $jsonProfile = $profiles['json'];
        $csvProfile = $profiles['csv'];

        $this->persistProfile($jsonProfile, $context);
        $this->persistProfile($csvProfile, $context);

        $jsonSourcePath = __DIR__ . '/import-export-v2-product-export-output.json';
        $csvSourcePath = __DIR__ . '/import-export-v2-product-export-output.csv';

        $this->assertInputFileExists($jsonSourcePath);
        $this->assertInputFileExists($csvSourcePath);

        $jsonInput = $this->prepareJsonImportSource($jsonSourcePath, 111, 'not-a-real-tax-id');
        $jsonBefore = $this->loadProductState($jsonInput['productNumber'], $context);
        $jsonImport = $this->runImport($jsonProfile, $jsonInput['path'], $context);
        $jsonAfter = $this->loadProductState($jsonInput['productNumber'], $context);

        $csvInput = $this->prepareCsvImportSource($csvSourcePath, 222, 'not-a-real-tax-id');
        $csvBefore = $this->loadProductState($csvInput['productNumber'], $context);
        $csvImport = $this->runImport($csvProfile, $csvInput['path'], $context);
        $csvAfter = $this->loadProductState($csvInput['productNumber'], $context);

        $jsonInvalidRecordsPath = $this->writeLocalInvalidRecordsOutput(
            'import-export-v2-product-import-invalid-records.json',
            $jsonImport['invalidRecordsContents']
        );
        $csvInvalidRecordsPath = $this->writeLocalInvalidRecordsOutput(
            'import-export-v2-product-import-invalid-records.csv',
            $csvImport['invalidRecordsContents']
        );

        echo "JSON updated values:\n";
        echo json_encode([
            'productNumber' => $jsonInput['productNumber'],
            'before' => $jsonBefore,
            'after' => $jsonAfter,
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n\n";
        $this->printInvalidRecords('JSON', $jsonImport, $jsonInvalidRecordsPath);

        echo "CSV updated values:\n";
        echo json_encode([
            'productNumber' => $csvInput['productNumber'],
            'before' => $csvBefore,
            'after' => $csvAfter,
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n\n";
        $this->printInvalidRecords('CSV', $csvImport, $csvInvalidRecordsPath);
    }

    private function persistProfile(ImportExportV2ProfileEntity $profile, Context $context): void
    {
        $profileRepository = $this->getProfileRepository();
        $existingId = $this->findExistingProfileId($profile->getTechnicalName(), $context);

        $profileData = [
            'id' => $existingId ?? Uuid::randomHex(),
            'technicalName' => $profile->getTechnicalName(),
            'entity' => $profile->getEntity(),
            'format' => $profile->getFormat(),
            'filters' => $profile->getFilters(),
            'recordPaths' => $profile->getRecordPaths(),
            'matchBy' => $profile->getMatchBy(),
            'fieldMappings' => $profile->getFieldMappings(),
        ];

        $profileRepository->upsert([$profileData], $context);
        $profile->setId($profileData['id']);
    }

    /**
     * @return array{
     *     invalidRecordsFilePath: ?string,
     *     invalidRecordsContents: ?string
     * }
     */
    private function runImport(ImportExportV2ProfileEntity $profile, string $sourcePath, Context $context): array
    {
        $runService = $this->getContainer()->get(RunService::class);
        \assert($runService instanceof RunService);

        $run = $runService->startImport($profile, $sourcePath, $context);

        while (true) {
            $runService->process($run->getId(), $context);

            $reloadedRun = $runService->getRun($run->getId(), $context);
            if (!$reloadedRun instanceof ImportExportV2RunEntity) {
                throw new \RuntimeException('Import run could not be reloaded.');
            }

            $run = $reloadedRun;

            if (\in_array($run->getState(), [
                ImportExportV2RunEntity::STATE_COMPLETED,
                ImportExportV2RunEntity::STATE_FAILED,
                ImportExportV2RunEntity::STATE_CANCELED,
            ], true)) {
                break;
            }
        }

        $fileService = $this->getContainer()->get(FileService::class);
        \assert($fileService instanceof FileService);

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        $invalidRecordsFilePath = null;
        $invalidRecordsContents = null;

        if ($run->getInvalidRecordsFileId() !== null) {
            $invalidFile = $fileService->getFile($run->getInvalidRecordsFileId(), $context);
            if ($invalidFile instanceof ImportExportV2FileEntity) {
                $invalidRecordsFilePath = $this->requireFilePath($invalidFile, 'Invalid records file path is missing.');
                $invalidRecordsContents = $filesystem->read($invalidRecordsFilePath);
            }
        }

        return [
            'invalidRecordsFilePath' => $invalidRecordsFilePath,
            'invalidRecordsContents' => $invalidRecordsContents,
        ];
    }

    private function printInvalidRecords(string $label, array $importResult, ?string $localPath): void
    {
        if (!\is_string($importResult['invalidRecordsFilePath']) || $importResult['invalidRecordsFilePath'] === '') {
            echo $label . " invalid records:\n";
            echo "none\n\n";

            return;
        }

        echo $label . " invalid records:\n";
        echo ($importResult['invalidRecordsContents'] ?? '') . "\n\n";
        if (\is_string($localPath) && $localPath !== '') {
            echo $label . " invalid records copied next to this script:\n";
            echo $localPath . "\n\n";
        }
    }

    /**
     * Updates the first record so we can verify a real product update, then
     * appends one deliberately invalid record to the same file so the import
     * run also produces invalid-record output.
     *
     * @return array{path: string, productNumber: string, updatedStock: int}
     */
    private function prepareJsonImportSource(string $sourcePath, int $updatedStock, string $invalidTaxId): array
    {
        $contents = file_get_contents($sourcePath);
        if (!\is_string($contents) || $contents === '') {
            throw new \RuntimeException(\sprintf('JSON import example source file "%s" is empty.', $sourcePath));
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded) || !isset($decoded[0]) || !\is_array($decoded[0])) {
            throw new \RuntimeException('JSON import example source does not contain any records.');
        }

        $productNumber = $decoded[0]['productNumber'] ?? null;
        if (!\is_string($productNumber) || $productNumber === '') {
            throw new \RuntimeException('JSON import example source does not contain a productNumber in the first record.');
        }

        $decoded[0]['stock'] = $updatedStock;
        $invalidRecord = $decoded[0];
        $invalidRecord['tax'] = ['id' => $invalidTaxId];
        $decoded[] = $invalidRecord;

        file_put_contents(
            $sourcePath,
            (string) json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)
        );

        return [
            'path' => $sourcePath,
            'productNumber' => $productNumber,
            'updatedStock' => $updatedStock,
        ];
    }

    /**
     * Updates the first CSV row so we can verify a real product update, then
     * appends one deliberately invalid row to the same file so the import run
     * also produces invalid-record output.
     *
     * @return array{path: string, productNumber: string, updatedStock: int}
     */
    private function prepareCsvImportSource(string $sourcePath, int $updatedStock, string $invalidTaxId): array
    {
        $lines = file($sourcePath, \FILE_IGNORE_NEW_LINES);
        if (!\is_array($lines) || \count($lines) < 2) {
            throw new \RuntimeException('CSV import example source does not contain any data rows.');
        }

        $header = str_getcsv($lines[0]);
        $firstRow = str_getcsv($lines[1]);

        $productNumberIndex = array_search('product_number', $header, true);
        $stockIndex = array_search('stock', $header, true);
        if (!\is_int($productNumberIndex) || !\is_int($stockIndex)) {
            throw new \RuntimeException('CSV import example source is missing the product_number or stock column.');
        }

        $productNumber = $firstRow[$productNumberIndex] ?? null;
        if (!\is_string($productNumber) || $productNumber === '') {
            throw new \RuntimeException('CSV import example source does not contain a product number in the first data row.');
        }

        $firstRow[$stockIndex] = (string) $updatedStock;
        $lines[1] = $this->encodeCsvRow($firstRow);
        $invalidRow = $firstRow;
        $taxIdIndex = array_search('tax_id', $header, true);
        if (!\is_int($taxIdIndex)) {
            throw new \RuntimeException('CSV import example source is missing the tax_id column.');
        }

        $invalidRow[$taxIdIndex] = $invalidTaxId;
        $lines[] = $this->encodeCsvRow($invalidRow);

        file_put_contents($sourcePath, implode("\n", $lines) . "\n");

        return [
            'path' => $sourcePath,
            'productNumber' => $productNumber,
            'updatedStock' => $updatedStock,
        ];
    }

    /**
     * @return array{id: string, productNumber: string, stock: mixed}
     */
    private function loadProductState(string $productNumber, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));

        $entity = $this->getProductRepository()->search($criteria, $context)->first();
        if (!$entity instanceof Entity) {
            throw new \RuntimeException(\sprintf('Product "%s" could not be found for import verification.', $productNumber));
        }

        return [
            'id' => $entity->getUniqueIdentifier(),
            'productNumber' => $productNumber,
            'stock' => $entity->get('stock'),
        ];
    }

    private function assertInputFileExists(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Import example source file "%s" does not exist.', $path));
        }
    }

    private function requireFilePath(ImportExportV2FileEntity $file, string $message): string
    {
        $path = $file->getPath();
        if (!\is_string($path) || $path === '') {
            throw new \RuntimeException($message);
        }

        return $path;
    }

    private function writeLocalInvalidRecordsOutput(string $filename, ?string $contents): ?string
    {
        if (!\is_string($contents) || $contents === '') {
            return null;
        }

        $path = __DIR__ . '/' . $filename;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return EntityRepository<object>
     */
    private function getProfileRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('import_export_v2_profile.repository');
        \assert($repository instanceof EntityRepository);

        return $repository;
    }

    /**
     * @return EntityRepository<object>
     */
    private function getProductRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('product.repository');
        \assert($repository instanceof EntityRepository);

        return $repository;
    }

    private function findExistingProfileId(string $technicalName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        $entity = $this->getProfileRepository()->search($criteria, $context)->first();

        return $entity instanceof ImportExportV2ProfileEntity ? $entity->getId() : null;
    }
    /**
     * @param list<string> $row
     */
    private function encodeCsvRow(array $row): string
    {
        $stream = fopen('php://temp', 'rb+');
        \assert(\is_resource($stream));

        fputcsv($stream, $row);
        rewind($stream);
        $encoded = stream_get_contents($stream);
        fclose($stream);

        \assert(\is_string($encoded));

        return rtrim($encoded, "\r\n");
    }
}

(new ImportExportV2ProductImport($kernel))->run();
