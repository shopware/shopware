<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - CopyBatchInput will be removed. Use WriteBatchInput instead.
 */
#[Package('core')]
class CopyBatchInput extends WriteBatchInput
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', WriteBatchInput::class)
        );

        parent::__construct(...\func_get_args());
    }
}
