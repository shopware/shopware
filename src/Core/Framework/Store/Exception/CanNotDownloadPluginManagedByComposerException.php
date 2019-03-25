<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CanNotDownloadPluginManagedByComposerException extends ShopwareHttpException
{
    protected $code = 'CAN-NOT-DOWNLOAD-PLUGIN-MANAGED-BY-SHOPWARE';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Can not download plugin. Please contact your system administrator. Error: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
