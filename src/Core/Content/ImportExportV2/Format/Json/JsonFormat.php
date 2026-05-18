<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Json;

use Shopware\Core\Content\ImportExportV2\Format\ExportWriterInterface;
use Shopware\Core\Content\ImportExportV2\Format\FormatInterface;
use Shopware\Core\Content\ImportExportV2\Format\ImportReaderInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class JsonFormat implements FormatInterface
{
    public function __construct(
        private readonly JsonImportReader $importReader,
        private readonly JsonExportWriter $exportWriter
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

    public function getImportReader(): ImportReaderInterface
    {
        return $this->importReader;
    }

    public function getExportWriter(): ExportWriterInterface
    {
        return $this->exportWriter;
    }
}
