<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Oidc;

use Shopware\Core\Framework\Log\Package;

/**
 * The validated id_token claims. The named properties are the subset used to resolve a Shopware
 * admin user; {@see self::getClaim()} exposes the raw payload for provider-specific claims such as
 * the groups claim used by the role mapping.
 *
 * @internal
 */
#[Package('framework')]
final class OidcClaims
{
    /**
     * @param array<string, mixed> $claims the full raw id_token payload
     */
    public function __construct(
        public readonly string $sub,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
        public readonly ?string $preferredUsername,
        private readonly array $claims = [],
    ) {
    }

    public function getClaim(string $name): mixed
    {
        return $this->claims[$name] ?? null;
    }
}
