<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Json;

use Shopware\Core\Content\ImportExportV2\Format\ExportFormatWriterInterface;
use Shopware\Core\Content\ImportExportV2\Format\FormatInterface;
use Shopware\Core\Content\ImportExportV2\Format\ImportFormatReaderInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final readonly class JsonFormat implements FormatInterface
{
    public function __construct(
        private JsonImportReader $importReader,
        private JsonExportWriter $exportWriter
    ) {
    }

    public function getName(): string
    {
        return 'json';
    }

    public function getMimeType(): string
    {
        return 'application/json';
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
