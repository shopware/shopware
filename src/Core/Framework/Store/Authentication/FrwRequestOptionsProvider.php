<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Context;

class FrwRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    private const SHOPWARE_TOKEN_HEADER = 'X-Shopware-Token';
    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';

    private AbstractStoreRequestOptionsProvider $optionsProvider;

    public function __construct(AbstractStoreRequestOptionsProvider $optionsProvider)
    {
        $this->optionsProvider = $optionsProvider;
    }

    public function getAuthenticationHeader(Context $context): array
    {
        $headers = $this->optionsProvider->getAuthenticationHeader($context);

        if (isset($headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER])) {
            $headers[self::SHOPWARE_TOKEN_HEADER] = $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER];
            unset($headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER]);
        }

        return $headers;
    }

    public function getDefaultQueryParameters(?Context $context, ?string $language = null): array
    {
        return $this->optionsProvider->getDefaultQueryParameters($context, $language);
    }
}
