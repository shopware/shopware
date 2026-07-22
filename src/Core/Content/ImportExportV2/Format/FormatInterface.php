<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format;

/**
 * @internal
 */
interface FormatInterface
{
    public function getName(): string;

    public function getMimeType(): string;

    public function getImportReader(): ImportFormatReaderInterface;

    public function getExportWriter(): ExportFormatWriterInterface;
}
