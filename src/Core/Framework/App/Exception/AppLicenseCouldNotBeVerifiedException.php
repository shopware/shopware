<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

/**
 * @internal only for use by the app-system
 */
class AppLicenseCouldNotBeVerifiedException extends AppRegistrationException
{
    public function getErrorCode(): string
    {
        return 'FRAMEWORK__APP_LICENSE_COULD_NOT_BE_VERIFIED';
    }
}
