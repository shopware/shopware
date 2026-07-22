<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;

/**
 * @internal
 */
interface ExportFormatWriterInterface
{
    public function initialize(ImportExportV2ProfileEntity $profile): string;

    /**
     * @param iterable<ImportExportRecord> $records
     */
    public function append(string $contents, iterable $records, ImportExportV2ProfileEntity $profile): string;

    public function finalize(string $contents, ImportExportV2ProfileEntity $profile): string;
}
