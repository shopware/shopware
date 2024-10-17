<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - CopyBatchInput will be removed. Use WriteBatchInputFactory instead.
 */
#[Package('core')]
class CopyBatchInputFactory extends WriteBatchInputFactory
{
    /**
     * @return array<WriteBatchInput>
     */
    public function fromDirectory(string $directory, string $target): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', WriteBatchInputFactory::class)
        );

        return parent::fromDirectory($directory, $target);
    }
}
