<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use League\Flysystem\FilesystemInterface;

class CsvFileWriter implements WriterInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var resource
     */
    private $buffer;

    /**
     * @var string
     */
    private $targetPath;

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
        $this->filesystem = $filesystem;
        $this->targetPath = $targetPath;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->initBuffer();
    }

    public function append(array $data): void
    {
        if (fputcsv($this->buffer, $data, $this->delimiter, $this->enclosure) === false) {
            throw new \RuntimeException('Could not write to buffer');
        }
    }

    public function flush(): void
    {
        $this->filesystem->putStream($this->targetPath, $this->buffer);
    }

    private function initBuffer(): void
    {
        $this->buffer = fopen('php://memory', 'r+b');
        stream_copy_to_stream($this->filesystem->readStream($this->targetPath), $this->buffer);
    }
}
