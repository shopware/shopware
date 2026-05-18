<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Format\ImportReaderInterface;
use Shopware\Core\Content\ImportExportV2\Format\ReadResult;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Support\FileService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CsvImportReader implements ImportReaderInterface
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function readChunk(
        ImportExportV2FileEntity $file,
        ImportExportV2ProfileEntity $profile,
        int $offset,
        int $limit,
        ?int $nextByteOffset = null
    ): ReadResult {
        $offset = max(0, $offset);
        $limit = max(1, $limit);

        $checkpoint = $this->resolveCheckpoint($offset, $nextByteOffset);

        // Chunked imports need stable local file semantics like fseek/ftell so
        // later chunks can resume from one persisted byte offset. We therefore
        // create a single local working copy and reuse it for all later chunks
        $localFile = fopen($this->fileService->getOrCreateLocalWorkingCopyPath($file), 'rb');
        if (!\is_resource($localFile)) {
            throw new \RuntimeException('Failed to open the local CSV working copy.');
        }

        try {
            $filePath = stream_get_meta_data($localFile)['uri'] ?? null;
            \assert(\is_string($filePath));

            $csvStream = fopen($filePath, 'rb');
            if (!\is_resource($csvStream)) {
                throw new \RuntimeException('Failed to open the local CSV working copy.');
            }

            try {
                $header = fgetcsv($csvStream);
                if (!\is_array($header)) {
                    throw ImportExportV2Exception::invalidFormatContent('csv', 'Could not read CSV header.');
                }

                if ($checkpoint !== null) {
                    fseek($csvStream, $checkpoint);
                }

                $records = [];
                $recordIndex = $checkpoint !== null ? $offset : 0;
                $isRescanningFromStart = $checkpoint === null && $offset > 0;
                $hasMore = false;
                $totalRecords = null;
                $nextByteOffset = null;

                while (true) {
                    $rowStartOffset = ftell($csvStream);
                    $row = fgetcsv($csvStream);
                    if ($row === false) {
                        break;
                    }

                    if ($row === [null]) {
                        continue;
                    }

                    $row = array_combine($header, $row);

                    $record = $this->mapRowToRecord($row, $profile);
                    if ($record === null) {
                        continue;
                    }

                    // Without a persisted byte checkpoint we have to rescan
                    // from the beginning and skip rows until we reach the
                    // requested logical offset.
                    if ($isRescanningFromStart && $recordIndex < $offset) {
                        ++$recordIndex;

                        continue;
                    }

                    if (\count($records) < $limit) {
                        $records[] = $record;
                        ++$recordIndex;
                        $nextByteOffset = ftell($csvStream);

                        continue;
                    }

                    $hasMore = true;
                    $nextByteOffset = $rowStartOffset;

                    break;
                }

                if (!$hasMore) {
                    $totalRecords = $recordIndex;
                }

                return new ReadResult(
                    $records,
                    $hasMore,
                    $totalRecords,
                    $hasMore ? $nextByteOffset : null
                );
            } finally {
                fclose($csvStream);
            }
        } finally {
            fclose($localFile);
        }
    }

    /**
     * CSV imports keep a lightweight byte offset so later chunks can continue
     * from the next unread row instead of rescanning the whole file.
     *
     * The run already stores the logical import progress in `offset`. The only
     * format-specific resume state we still need is the physical byte position
     * of the next unread CSV row in the file.
     */
    private function resolveCheckpoint(int $offset, ?int $nextByteOffset): ?int
    {
        if ($offset === 0) {
            return null;
        }

        if (!\is_int($nextByteOffset) || $nextByteOffset < 0) {
            return null;
        }

        return $nextByteOffset;
    }

    /**
     * @param array<string, string|null> $row
     *
     * @return ImportExportRecord|null
     */
    private function mapRowToRecord(array $row, ImportExportV2ProfileEntity $profile): ?ImportExportRecord
    {
        $payload = [];

        $hasValues = false;
        foreach ($profile->getFieldMappings() as $mapping) {
            $column = $this->getMappingValue($mapping, 'column');
            $value = $row[$column] ?? null;
            if (!\is_string($value) || trim($value) === '') {
                continue;
            }

            $hasValues = true;
            $separator = $this->getOptionalMappingValue($mapping, 'separator');
            $path = $this->getMappingValue($mapping, 'path');
            if ($separator !== null) {
                CsvMappingHelper::writeListValuesToRecordListPath(
                    $payload,
                    $path,
                    array_values(array_filter(array_map('trim', explode($separator, $value))))
                );

                continue;
            }

            CsvMappingHelper::writeValueToRecordPath(
                $payload,
                $path,
                $this->castValue($value, $this->getMappingType($mapping))
            );
        }

        return $hasValues ? new ImportExportRecord($profile->getEntity(), $payload) : null;
    }

    private function castValue(string $value, string $type): bool|int|string
    {
        return match ($type) {
            'bool' => \in_array(strtolower($value), ['1', 'true', 'yes'], true),
            'int' => (int) $value,
            default => $value,
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
