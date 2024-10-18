<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - CopyBatchInput will be removed. Use WriteBatchInput instead.
 */
#[Package('core')]
class CopyBatch extends WriteBatch
{
    public static function copy(FilesystemOperator $filesystem, CopyBatchInput ...$files): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', WriteBatch::class . '::write')
        );

        parent::write($filesystem, ...$files);
    }
}
