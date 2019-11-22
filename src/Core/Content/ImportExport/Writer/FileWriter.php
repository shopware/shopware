<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use League\Flysystem\FilesystemInterface;

abstract class FileWriter
{
    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $targetPath;

    /**
     * @var resource
     */
    protected $tempFile;

    /**
     * @var string
     */
    protected $tempPath;

    /**
     * @var resource
     */
    protected $buffer;

    public function __construct(FilesystemInterface $filesystem, string $targetPath)
    {
        $this->filesystem = $filesystem;
        $this->targetPath = $targetPath;
        $this->tempPath = sys_get_temp_dir() . '/' . md5($targetPath);
        $this->tempFile = \fopen($this->tempPath, 'a+b');
        $this->initBuffer();
    }

    public function flush(): void
    {
        rewind($this->buffer);

        $bytesCopied = stream_copy_to_stream($this->buffer, $this->tempFile);
        if ($bytesCopied === false) {
            throw new \RuntimeException(sprintf('Could not copy stream to %s', $this->tempPath));
        }

        $this->initBuffer();
    }

    public function finish(): void
    {
        $this->flush();
        $this->filesystem->putStream($this->targetPath, $this->tempFile);
        fclose($this->tempFile);
        unlink($this->tempPath);
    }

    private function initBuffer(): void
    {
        if (\is_resource($this->buffer)) {
            fclose($this->buffer);
        }
        $this->buffer = fopen('php://memory', 'r+b');
    }
}
