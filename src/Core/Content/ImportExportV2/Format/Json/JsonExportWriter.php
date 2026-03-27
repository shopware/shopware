<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Json;

use Shopware\Core\Content\ImportExportV2\Format\ExportFormatWriterInterface;
use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class JsonExportWriter implements ExportFormatWriterInterface
{
    public function initialize(ImportExportV2ProfileEntity $profile): string
    {
        return '[]';
    }

    public function append(string $contents, iterable $records, ImportExportV2ProfileEntity $profile): string
    {
        $existingPayload = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($existingPayload));

        $newPayload = array_map(
            static fn (ImportExportRecord $record) => $record->jsonSerialize(),
            \is_array($records) ? $records : iterator_to_array($records)
        );

        return (string) json_encode([...$existingPayload, ...$newPayload], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
    }

    public function finalize(string $contents, ImportExportV2ProfileEntity $profile): string
    {
        return $contents;
    }
}
