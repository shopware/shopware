<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CanNotDownloadPluginManagedByComposerException extends ShopwareHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Can not download plugin. Please contact your system administrator. Error: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_CANNOT_DOWNLOAD_PLUGIN_MANAGED_BY_SHOPWARE';
    }
}
