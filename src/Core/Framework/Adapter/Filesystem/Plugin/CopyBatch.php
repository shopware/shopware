<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\FilesystemOperator;

/**
 * @package core
 */
class CopyBatch
{
    public static function copy(FilesystemOperator $filesystem, CopyBatchInput ...$files): void
    {
        foreach ($files as $batchInput) {
            if (\is_resource($batchInput->getSourceFile())) {
                $handle = $batchInput->getSourceFile();
            } else {
                $handle = fopen($batchInput->getSourceFile(), 'rb');
            }

            foreach ($batchInput->getTargetFiles() as $targetFile) {
                $filesystem->writeStream($targetFile, $handle);
            }

            if (\is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
