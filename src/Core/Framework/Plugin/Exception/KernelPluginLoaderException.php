<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed. Use \Shopware\Core\Framework\Plugin\PluginException::kernelPluginLoaderError instead
 */
#[Package('framework')]
class KernelPluginLoaderException extends ShopwareHttpException
{
    public function __construct(
        string $plugin,
        string $reason
    ) {
        parent::__construct(
            'Failed to load plugin "{{ plugin }}". Reason: {{ reason }}',
            ['plugin' => $plugin, 'reason' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__KERNEL_PLUGIN_LOADER_ERROR';
    }
}
