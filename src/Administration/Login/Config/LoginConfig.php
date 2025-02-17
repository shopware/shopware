<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class LoginConfig
{
    /**
     * @param non-empty-string $clientId
     * @param non-empty-string $clientSecret
     * @param non-empty-string $redirectUri
     * @param non-empty-string $baseUrl
     * @param non-empty-string $authorizeEndpoint
     * @param non-empty-string $tokenEndpoint
     */
    public function __construct(
        public readonly bool $useDefault,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly string $baseUrl,
        public readonly string $authorizeEndpoint,
        public readonly string $tokenEndpoint,
    ) {
    }
}
