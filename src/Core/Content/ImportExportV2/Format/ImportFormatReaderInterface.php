<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;

/**
 * @internal
 */
interface ImportFormatReaderInterface
{
    /**
     * For the prototype, readers may still parse the full file internally.
     * The important contract is that the worker only receives the requested slice.
     *
     * @return array{records:list<array<string, mixed>>, nextOffset:int, totalRecords:int, hasMore:bool}
     */
    public function readChunk(
        string $contents,
        ImportExportV2ProfileEntity $profile,
        int $offset,
        int $limit
    ): array;
}
