<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppRegistrationException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'FRAMEWORK__APP_REGISTRATION_FAILED';
    }
}
