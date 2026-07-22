<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\Format\ImportFormatReaderInterface;
use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Support\ArrayPathAccessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CsvImportReader implements ImportFormatReaderInterface
{
    public function readChunk(
        string $contents,
        ImportExportV2ProfileEntity $profile,
        int $offset,
        int $limit
    ): array {
        $file = new \SplTempFileObject();
        $file->fwrite($contents);
        $file->rewind();

        $header = $file->fgetcsv(separator: ',', enclosure: '"', escape: '\\');
        if (!\is_array($header)) {
            throw ImportExportV2Exception::invalidFormatContent('csv', 'Could not read CSV header.');
        }

        $records = [];

        while (($row = $file->fgetcsv(separator: ',', enclosure: '"', escape: '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $row = array_combine($header, $row);
            if (!\is_array($row)) {
                throw ImportExportV2Exception::invalidFormatContent('csv', 'CSV row does not match the header column count.');
            }

            $record = [
                'entity' => $profile->getEntity(),
                'identifier' => [],
                'payload' => [],
            ];

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
                    // CSV stays flat at the file level, so list columns are expanded back into record paths here.
                    ArrayPathAccessor::setList(
                        $record,
                        $path,
                        array_values(array_filter(array_map('trim', explode($separator, $value))))
                    );

                    continue;
                }

                ArrayPathAccessor::set(
                    $record,
                    $path,
                    $this->castValue($value, $this->getMappingType($mapping))
                );
            }

            // Empty CSV rows are ignored so they do not show up as empty import records.
            if ($hasValues) {
                $records[] = $record;
            }
        }

        $chunk = \array_slice($records, $offset, $limit);
        $nextOffset = $offset + \count($chunk);

        return [
            'records' => $chunk,
            'nextOffset' => $nextOffset,
            'totalRecords' => \count($records),
            'hasMore' => $nextOffset < \count($records),
        ];
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
