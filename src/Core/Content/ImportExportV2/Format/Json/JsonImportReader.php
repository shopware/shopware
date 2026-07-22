<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Json;

use Shopware\Core\Content\ImportExportV2\Format\ImportFormatReaderInterface;
use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class JsonImportReader implements ImportFormatReaderInterface
{
    public function readChunk(
        string $contents,
        ImportExportV2ProfileEntity $profile,
        int $offset,
        int $limit
    ): array {
        try {
            $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ImportExportV2Exception::invalidFormatContent('json', $exception->getMessage());
        }

        if (!\is_array($decoded)) {
            throw ImportExportV2Exception::invalidFormatContent('json', 'Expected a JSON array of records.');
        }

        $records = [];
        foreach ($decoded as $record) {
            if (!\is_array($record)) {
                throw ImportExportV2Exception::invalidFormatContent('json', 'Every record must be an object.');
            }

            // JSON is already close to the shared record shape, so the reader only fills in omitted defaults.
            $record['entity'] ??= $profile->getEntity();
            $record['identifier'] ??= [];
            $record['payload'] ??= [];

            $records[] = $record;
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
}
