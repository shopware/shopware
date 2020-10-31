<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

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
            $handle = null;
            
            foreach ($batchInput->getTargetFiles() as $targetFile) {                
                if (is_resource($batchInput->getSourceFile())) {
                    $handle = $batchInput->getSourceFile();
                } elseif (!is_resource($handle)) {
                    $handle = fopen($batchInput->getSourceFile(), 'rb');
                }

                $this->filesystem->putStream($targetFile, $handle);
            }

            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
