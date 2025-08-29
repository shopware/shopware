<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class LoginConfig
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
        public bool $useDefault,
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
        public string $baseUrl,
        public string $authorizePath,
        public string $tokenPath,
        public string $jwksPath,
        public string $scope,
        public string $registerUrl,
    ) {
    }
}
