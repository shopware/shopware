<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.5.0 - CsrfNotEnabledException will be removed as the csrf system will be removed in favor for the samesite approach
 */
class CsrfNotEnabledException extends ShopwareHttpException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        parent::__construct('CSRF protection is not enabled.');
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return 'FRAMEWORK__CSRF_NOT_ENABLED';
    }
}
