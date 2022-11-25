<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @package merchant-services
 *
 * @deprecated tag:v6.5.0 - Will be replaced with \Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException
 */
class ExtensionRequiresNewPrivilegesException extends ShopwareHttpException
{
    /**
     * @param string[] $privileges
     */
    public static function fromPrivilegeList(string $appName, array $privileges): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'ExtensionUpdateRequiresConsentAffirmationException')
        );

        return new self(
            'Updating "{{app}}" requires new privileges "{{privileges}}".',
            [
                'app' => $appName,
                'privileges' => implode(';', $privileges),
            ]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'ExtensionUpdateRequiresConsentAffirmationException')
        );

        return 'FRAMEWORK__EXTENSION_REQUIRES_NEW_PRIVILEGES';
    }
}
