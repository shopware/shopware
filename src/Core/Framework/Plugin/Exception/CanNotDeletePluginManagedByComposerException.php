<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CanNotDeletePluginManagedByComposerException extends ShopwareHttpException
{
    protected $code = 'CAN-NOT-DELETE-PLUGIN-MANAGED-BY-SHOPWARE';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Can not delete plugin. Please contact your system administrator. Error: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
