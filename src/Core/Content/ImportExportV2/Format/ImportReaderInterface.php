<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;

interface ImportReaderInterface
{
    /**
     * @param int|null $nextByteOffset This parameter is used to optimize reading large files by providing a byte offset
     *                                 for the next read operation, allowing the reader to seek directly to that position
     *                                 in the file for the next chunk of data.
     */
    public function readChunk(
        ImportExportV2FileEntity $file,
        ImportExportV2ProfileEntity $profile,
        int $offset,
        int $limit,
        ?int $nextByteOffset = null
    ): ReadResult;
}
