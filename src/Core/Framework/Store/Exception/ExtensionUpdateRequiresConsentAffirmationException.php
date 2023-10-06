<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - will be removed. Use StoreException::extensionUpdateRequiresConsentAffirmationException instead.
 */
#[Package('services-settings')]
class ExtensionUpdateRequiresConsentAffirmationException extends StoreException
{
    public function __construct(
        string $message,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use StoreException::extensionUpdateRequiresConsentAffirmationException instead.')
        );

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            StoreException::EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION,
            $message,
            $parameters,
            $e
        );
    }

    /**
     * @param array<string, array<string, mixed>> $deltas
     */
    public static function fromDelta(string $appName, array $deltas): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use StoreException::extensionUpdateRequiresConsentAffirmationException instead.')
        );

        return new self('Updating app "{{appName}}" requires a renewed consent affirmation.', compact('appName', 'deltas'));
    }
}
