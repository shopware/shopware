<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Struct\Config;

abstract class AbstractFileWriter extends AbstractWriter
{
    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

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

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->initTempFile();
        $this->initBuffer();
    }

    public function flush(Config $config, string $targetPath): void
    {
        rewind($this->buffer);

        $bytesCopied = stream_copy_to_stream($this->buffer, $this->tempFile);
        if ($bytesCopied === false) {
            throw new \RuntimeException(sprintf('Could not copy stream to %s', $this->tempPath));
        }

        if (ftell($this->tempFile) > 0) {
            $this->filesystem->putStream($targetPath, $this->tempFile);
        }

        $this->initBuffer();
    }

    public function finish(Config $config, string $targetPath): void
    {
        $this->flush($config, $targetPath);

        fclose($this->tempFile);
        unlink($this->tempPath);

        $this->initTempFile();
    }

    private function initTempFile(): void
    {
        $this->tempPath = tempnam(sys_get_temp_dir(), '');
        $this->tempFile = \fopen($this->tempPath, 'a+b');
    }

    private function initBuffer(): void
    {
        if (\is_resource($this->buffer)) {
            fclose($this->buffer);
        }
        $this->buffer = fopen('php://memory', 'r+b');
    }
}
