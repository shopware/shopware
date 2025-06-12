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
     * @param non-empty-string $authorizePath
     * @param non-empty-string $tokenPath
     * @param non-empty-string $jwksPath
     * @param non-empty-string $scope
     * @param non-empty-string $registerUrl
     */
    public function __construct(
        public readonly bool $useDefault,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly string $baseUrl,
        public readonly string $authorizePath,
        public readonly string $tokenPath,
        public readonly string $jwksPath,
        public readonly string $scope,
        public readonly string $registerUrl,
    ) {
    }
}
