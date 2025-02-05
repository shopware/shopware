<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class LoginConfig
{
    public function __construct(
        public readonly bool $useDefault,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly string $baseUrl,
    ) {
    }
}
