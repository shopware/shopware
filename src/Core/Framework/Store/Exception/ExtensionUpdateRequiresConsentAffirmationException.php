<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

/**
 * @package merchant-services
 *
 * @deprecated tag:v6.5.0 - reason:class-hierarchy-change - Will only extend from Shopware\Core\Framework\ShopwareHttpException
 *
 * @internal
 */
class ExtensionUpdateRequiresConsentAffirmationException extends ExtensionRequiresNewPrivilegesException
{
    /**
     * @param array<string, array<string, mixed>> $deltas
     */
    public static function fromDelta(string $appName, array $deltas): self
    {
        return new self(
            'Updating app "{{appName}}" requires a renewed consent affirmation.',
            compact('appName', 'deltas')
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION';
    }
}
