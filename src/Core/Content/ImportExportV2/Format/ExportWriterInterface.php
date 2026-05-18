<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;

interface ExportWriterInterface
{
    /**
     * @param iterable<ImportExportRecord> $records
     */
    public function append(
        ImportExportV2ProfileEntity $profile,
        ImportExportV2FileEntity $file,
        iterable $records
    ): void;
}
