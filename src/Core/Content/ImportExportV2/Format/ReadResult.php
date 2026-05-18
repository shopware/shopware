<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
final readonly class ReadResult
{
    /**
     * @param list<ImportExportRecord> $records
     * @param int|null                 $totalRecords   A streamed reader may not know the full record count yet.
     *                                                 In that case it keeps returning chunks and only fills
     *                                                 `totalRecords` once it reaches the end.
     * @param int|null                 $nextByteOffset For formats that support byte-offset checkpoints, this
     *                                                 points to the start of the next unread chunk in the
     *                                                 source file.
     */
    public function __construct(
        public array $records,
        public bool $hasMore,
        public ?int $totalRecords = null,
        public ?int $nextByteOffset = null
    ) {
    }
}
