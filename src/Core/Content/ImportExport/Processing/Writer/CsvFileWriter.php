<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class CsvFileWriter extends AbstractFileWriter
{
    /**
     * Characters that make a spreadsheet application interpret a cell as a formula.
     * A value starting with one of these is prefixed so the cell is treated as text.
     *
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     */
    private const FORMULA_TRIGGER_CHARACTERS = ['=', '+', '-', '@', "\t", "\r", "\n"];

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
        $this->writeToBuffer(array_map($this->escapeFormula(...), array_values($data)));
    }

    /**
     * Prevents CSV / formula injection: a cell whose value starts with a formula trigger
     * character is prefixed with a single quote, so a spreadsheet application treats it as
     * text instead of evaluating it when the exported file is opened.
     *
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     */
    private function escapeFormula(mixed $value): mixed
    {
        if (!\is_string($value) || $value === '') {
            return $value;
        }

        if (\in_array($value[0], self::FORMULA_TRIGGER_CHARACTERS, true)) {
            return '\'' . $value;
        }

        return $value;
    }

    /**
     * @param list<string>|list<mixed> $data
     */
    private function writeToBuffer(array $data): void
    {
        if (fputcsv($this->buffer, $data, $this->delimiter, $this->enclosure, '\\') === false) {
            throw ImportExportException::couldNotWriteToBuffer();
        }
    }

    private function loadConfig(Config $config): void
    {
        $this->delimiter = $config->get('delimiter') ?? $this->delimiter;
        $this->enclosure = $config->get('enclosure') ?? $this->enclosure;
    }
}
