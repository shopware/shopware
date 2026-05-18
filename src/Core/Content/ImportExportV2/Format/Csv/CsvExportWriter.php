<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Format\ExportWriterInterface;
use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CsvExportWriter implements ExportWriterInterface
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    /**
     * @param iterable<ImportExportRecord> $records
     */
    public function append(
        ImportExportV2ProfileEntity $profile,
        ImportExportV2FileEntity $file,
        iterable $records
    ): void {
        $localFile = $this->createLocalWorkingCopy($file);

        try {
            $filePath = stream_get_meta_data($localFile)['uri'] ?? null;
            \assert(\is_string($filePath));

            $csvFile = new \SplFileObject($filePath, 'a+b');
            $csvFile->setCsvControl(',', '"', '\\');

            if ($csvFile->getSize() === 0) {
                $headers = array_map(
                    fn (array $mapping): string => $this->getMappingValue($mapping, 'column'),
                    $profile->getFieldMappings()
                );

                $csvFile->fputcsv($headers);
            } else {
                $csvFile->fseek(0, \SEEK_END);
            }

            foreach ($records as $record) {
                \assert($record instanceof ImportExportRecord);

                $csvFile->fputcsv($this->buildRow($record, $profile));
            }

            rewind($localFile);

            $this->writeStream($file, $localFile);
        } finally {
            fclose($localFile);
        }
    }

    /**
     * @return resource
     */
    private function createLocalWorkingCopy(ImportExportV2FileEntity $file)
    {
        $inputStream = $this->openReadStream($file);
        $localFile = tmpfile();

        if (!\is_resource($localFile)) {
            fclose($inputStream);

            throw new \RuntimeException('Failed to open a temporary file for CSV export.');
        }

        try {
            stream_copy_to_stream($inputStream, $localFile);
            rewind($localFile);
        } finally {
            fclose($inputStream);
        }

        return $localFile;
    }

    /**
     * @return resource
     */
    private function openReadStream(ImportExportV2FileEntity $file)
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        $stream = $this->filesystem->readStream($path);
        if (!\is_resource($stream)) {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function writeStream(ImportExportV2FileEntity $file, $stream): void
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        $this->filesystem->writeStream($path, $stream);
    }

    /**
     * @return list<string>
     */
    private function buildRow(ImportExportRecord $record, ImportExportV2ProfileEntity $profile): array
    {
        $row = [];
        $payload = $record->payload;

        foreach ($profile->getFieldMappings() as $mapping) {
            $separator = $this->getOptionalMappingValue($mapping, 'separator');
            $path = $this->getMappingValue($mapping, 'path');

            if ($separator !== null) {
                $row[] = implode($separator, CsvMappingHelper::readListValuesFromRecordListPath($payload, $path));

                continue;
            }

            $value = CsvMappingHelper::readValueFromRecordPath($payload, $path);
            $row[] = \is_scalar($value) || $value === null ? $this->formatValue($value, $this->getMappingType($mapping)) : '';
        }

        return $row;
    }

    private function formatValue(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value === true ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function getMappingValue(array $mapping, string $key): string
    {
        $value = $mapping[$key] ?? null;
        \assert(\is_string($value));

        return $value;
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function getOptionalMappingValue(array $mapping, string $key): ?string
    {
        $value = $mapping[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function getMappingType(array $mapping): string
    {
        return $this->getOptionalMappingValue($mapping, 'type') ?? 'string';
    }
}
