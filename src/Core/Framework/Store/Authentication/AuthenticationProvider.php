<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @decrecated tag:v6.5.0 - Will be removed. Use AbstractStoreRequestOptionsProvider instead
 */
class AuthenticationProvider extends AbstractAuthenticationProvider
{
    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';

    private AbstractStoreRequestOptionsProvider $optionProvider;

    public function __construct(
        AbstractStoreRequestOptionsProvider $storeRequestOptionsProvider
    ) {
        $this->optionProvider = $storeRequestOptionsProvider;
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeader(Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(
                __CLASS__,
                'v6.5.0.0',
                AbstractStoreRequestOptionsProvider::class
            )
        );

        return $this->optionProvider->getAuthenticationHeader($context);
    }

    public function getUserStoreToken(Context $context): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(
                __CLASS__,
                'v6.5.0.0',
                AbstractStoreRequestOptionsProvider::class
            )
        );

        $headers = $this->optionProvider->getAuthenticationHeader($context);

        return $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER] ?? null;
    }
}
