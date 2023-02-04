<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
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
