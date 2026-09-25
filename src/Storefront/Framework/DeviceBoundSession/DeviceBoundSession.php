<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Shopware\Core\Framework\Log\Package;

/**
 * A storefront session whose cookie is bound to a key held by the customer's device.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class DeviceBoundSession
{
    /**
     * @param array<string, mixed> $publicKey JWK of the device key
     */
    public function __construct(
        public readonly string $id,
        public readonly string $contextToken,
        public readonly array $publicKey,
        public readonly string $cookieHash,
        public readonly ?string $previousCookieHash,
        public readonly ?string $challenge,
        public readonly \DateTimeImmutable $refreshedAt,
    ) {
    }
}
