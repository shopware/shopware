<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Context;

/**
 * @internal
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

    public function getAuthenticationHeader(Context $context): array
    {
        return $this->optionProvider->getAuthenticationHeader($context);
    }

    public function getUserStoreToken(Context $context): string
    {
        $headers = $this->optionProvider->getAuthenticationHeader($context);

        return $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER];
    }
}
