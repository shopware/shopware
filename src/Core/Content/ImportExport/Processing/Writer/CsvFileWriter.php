<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Struct\Config;

class CsvFileWriter extends AbstractFileWriter
{
    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var string
     */
    private $enclosure;

    public function __construct(FilesystemInterface $filesystem, string $delimiter = ';', string $enclosure = '"')
    {
        parent::__construct($filesystem);
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    public function append(Config $config, array $data, int $index): void
    {
        $this->loadConfig($config);

        if ($index === 0) {
            $this->writeToBuffer(array_keys($data));
        }
        $this->writeToBuffer(array_values($data));
    }

    private function writeToBuffer(array $data): void
    {
        if (fputcsv($this->buffer, $data, $this->delimiter, $this->enclosure) === false) {
            throw new \RuntimeException('Could not write to buffer');
        }
    }

    private function loadConfig(Config $config): void
    {
        $this->delimiter = $config->get('delimiter') ?? $this->delimiter;
        $this->enclosure = $config->get('enclosure') ?? $this->enclosure;
    }
}
