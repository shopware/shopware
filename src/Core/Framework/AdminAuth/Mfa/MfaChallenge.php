<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Mfa;

use Shopware\Core\Framework\Log\Package;

/**
 * In-memory representation of a pending second-factor challenge row.
 * Part of the public second-factor verifier contract.
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @final
 */
#[Package('framework')]
class MfaChallenge
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $pendingJti,
        public readonly array $allowedMethods,
        public readonly int $attempts,
        public readonly bool $consumed,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
