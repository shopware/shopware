<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\Format\ExportFormatWriterInterface;
use Shopware\Core\Content\ImportExportV2\Format\FormatInterface;
use Shopware\Core\Content\ImportExportV2\Format\ImportFormatReaderInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final readonly class CsvFormat implements FormatInterface
{
    public function __construct(
        private CsvImportReader $importReader,
        private CsvExportWriter $exportWriter
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

    public function getImportReader(): ImportFormatReaderInterface
    {
        return $this->importReader;
    }

    public function getExportWriter(): ExportFormatWriterInterface
    {
        return $this->exportWriter;
    }
}
