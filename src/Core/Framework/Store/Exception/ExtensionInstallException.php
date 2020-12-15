<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class ExtensionInstallException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EXTENSION_INSTALL_EXCEPTION';
    }
}
