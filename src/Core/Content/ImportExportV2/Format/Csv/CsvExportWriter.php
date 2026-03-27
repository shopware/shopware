<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\Format\ExportFormatWriterInterface;
use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Support\ArrayPathAccessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CsvExportWriter implements ExportFormatWriterInterface
{
    public function initialize(ImportExportV2ProfileEntity $profile): string
    {
        $file = new \SplTempFileObject();
        $headers = array_map(fn (array $mapping): string => $this->getMappingValue($mapping, 'column'), $profile->getFieldMappings());
        $file->fputcsv($headers, ',', '"', '\\');
        $file->rewind();

        return (string) $file->fgets();
    }

    public function append(string $contents, iterable $records, ImportExportV2ProfileEntity $profile): string
    {
        $file = new \SplTempFileObject();
        $file->fwrite($contents);
        $file->fseek(0, \SEEK_END);

        foreach ($records as $record) {
            \assert($record instanceof ImportExportRecord);

            $row = [];
            $serialized = $record->jsonSerialize();
            foreach ($profile->getFieldMappings() as $mapping) {
                $separator = $this->getOptionalMappingValue($mapping, 'separator');
                $path = $this->getMappingValue($mapping, 'path');
                if ($separator !== null) {
                    // Repeated values are flattened into one column so CSV can still represent simple list relations.
                    $row[] = implode($separator, ArrayPathAccessor::getList($serialized, $path));

                    continue;
                }

                $value = ArrayPathAccessor::get($serialized, $path);
                $row[] = \is_scalar($value) || $value === null ? $this->formatValue($value, $this->getMappingType($mapping)) : '';
            }

            $file->fputcsv($row, ',', '"', '\\');
        }

        $file->rewind();
        $contents = '';
        while (!$file->eof()) {
            $contents .= $file->fgets();
        }

        \assert(\is_string($contents));

        return $contents;
    }

    public function finalize(string $contents, ImportExportV2ProfileEntity $profile): string
    {
        return $contents;
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
