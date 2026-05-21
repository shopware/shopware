<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Embedded;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Value object representing an active EP session — short-lived, bound to
 * a single (cart, host_origin) pair so the embedded page can validate
 * postMessage origins and the REST surface can authorise embedded
 * requests via `X-UCP-Embedded-Session`.
 *
 * @internal
 */
#[Package('framework')]
final class EmbeddedSession
{
    public function __construct(
        public readonly string $id,
        public readonly string $token,
        public readonly string $cartId,
        public readonly string $salesChannelId,
        public readonly string $hostOrigin,
        public readonly string $kind,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }
}
