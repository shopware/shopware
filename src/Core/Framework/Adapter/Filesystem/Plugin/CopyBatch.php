<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CopyBatch implements PluginInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function getMethod(): string
    {
        return 'copyBatch';
    }

    public function setFilesystem(FilesystemInterface $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    public function handle(CopyBatchInput ...$files): void
    {
        foreach ($files as $batchInput) {
            $resource = $handle = $batchInput->getSourceFile();

            foreach ($batchInput->getTargetFiles() as $targetFile) {
                if (!\is_resource($handle)) {
                    $resource = fopen($handle, 'rb');

                    if ($resource === false) {
                        throw new \RuntimeException(sprintf('Could not open file "%s"', $handle));
                    }
                } else {
                    $resource = $handle;
                }

                $this->filesystem->putStream($targetFile, $resource);
            }

            if (\is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}
