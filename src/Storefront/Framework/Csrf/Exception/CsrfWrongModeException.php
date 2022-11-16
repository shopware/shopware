<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.5.0 - CsrfWrongModeException will be removed as the csrf system will be removed in favor for the samesite approach
 */
class CsrfWrongModeException extends ShopwareHttpException
{
    public function __construct(string $requiredMode)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        parent::__construct(
            'CSRF has the wrong mode. Please make sure the mode is set to "{{mode}}"',
            ['mode' => $requiredMode]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return 'FRAMEWORK__CSRF_WRONG_MODE';
    }
}
