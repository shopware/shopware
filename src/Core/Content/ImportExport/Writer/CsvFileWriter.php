<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use League\Flysystem\FilesystemInterface;

class CsvFileWriter extends FileWriter implements WriterInterface
{
    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var string
     */
    private $enclosure;

    public function __construct(FilesystemInterface $filesystem, string $targetPath, string $delimiter = ';', string $enclosure = '"')
    {
        parent::__construct($filesystem, $targetPath);
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    public function append(array $data, int $index): void
    {
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
}
