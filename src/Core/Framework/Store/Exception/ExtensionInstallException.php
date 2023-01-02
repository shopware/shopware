<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('merchant-services')]
class ExtensionInstallException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EXTENSION_INSTALL_EXCEPTION';
    }
}
