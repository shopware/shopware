<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CopyBatch
{
    public static function copy(FilesystemOperator $filesystem, CopyBatchInput ...$files): void
    {
        foreach ($files as $batchInput) {
            $handle = $batchInput->getSourceFile();

            foreach ($batchInput->getTargetFiles() as $targetFile) {
                if (!\is_resource($batchInput->getSourceFile())) {
                    $handle = fopen($batchInput->getSourceFile(), 'rb');
                }

                $filesystem->writeStream($targetFile, $handle);
            }

            if (\is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
