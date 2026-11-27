<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Util\FormulaEscaper;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class CsvFileWriter extends AbstractFileWriter
{
    /**
     * @internal
     */
    public function __construct(
        FilesystemOperator $filesystem,
        private string $delimiter = ';',
        private string $enclosure = '"'
    ) {
        parent::__construct($filesystem);
    }

    public function append(Config $config, array $data, int $index): void
    {
        $this->loadConfig($config);

        if ($index === 0) {
            // The header row holds the column keys used to map the data back on import, so it must not be escaped.
            $this->writeToBuffer(array_keys($data));
        }
        $this->writeToBuffer(array_map(FormulaEscaper::escape(...), array_values($data)));
    }

    /**
     * @param list<string>|list<mixed> $data
     */
    private function writeToBuffer(array $data): void
    {
        // Spreadsheet applications read CSV as defined in RFC 4180, which has no escape character.
        // With one, a value containing a backslash before a quote would end its cell early and start new cells.
        if (fputcsv($this->buffer, $data, $this->delimiter, $this->enclosure, '') === false) {
            throw ImportExportException::couldNotWriteToBuffer();
        }
    }

    private function loadConfig(Config $config): void
    {
        $this->delimiter = $config->get('delimiter') ?? $this->delimiter;
        $this->enclosure = $config->get('enclosure') ?? $this->enclosure;
    }
}
