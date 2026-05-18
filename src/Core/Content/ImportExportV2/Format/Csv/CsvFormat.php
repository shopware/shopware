<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\Format\ExportWriterInterface;
use Shopware\Core\Content\ImportExportV2\Format\FormatInterface;
use Shopware\Core\Content\ImportExportV2\Format\ImportReaderInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CsvFormat implements FormatInterface
{
    public function __construct(
        private readonly CsvImportReader $importReader,
        private readonly CsvExportWriter $exportWriter
    ) {
    }

    public function getName(): string
    {
        return 'csv';
    }

    public function getMimeType(): string
    {
        return 'text/csv';
    }

    public function getImportReader(): ImportReaderInterface
    {
        return $this->importReader;
    }

    public function getExportWriter(): ExportWriterInterface
    {
        return $this->exportWriter;
    }
}
