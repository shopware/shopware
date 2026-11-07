<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signs and verifies short-lived tokens that Shopware issues and reads back itself, with HMAC-SHA256,
 * a constant-time compare and expiry against an injected clock.
 *
 * The caller owns the token format: it builds the signed data from validated fields, ideally with a
 * purpose label so a signature for one token type never verifies as another, and parses its own tokens.
 * Do not use it for signatures exchanged with apps or other systems; their format is a protocol.
 */
#[Package('framework')]
class HmacSigner
{
    private const ALGORITHM = 'sha256';

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return string the signature, base64url encoded without padding
     */
    public function sign(string $data): string
    {
        return Base64::urlEncode(hash_hmac(self::ALGORITHM, $data, $this->secret, true));
    }

    public function verify(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }

    /**
     * For token formats that already carry hex signatures.
     *
     * @return string the signature, lowercase hex encoded
     */
    public function signHex(string $data): string
    {
        return hash_hmac(self::ALGORITHM, $data, $this->secret);
    }

    public function verifyHex(string $data, string $signature): bool
    {
        return hash_equals($this->signHex($data), $signature);
    }

    /**
     * @return \DateTimeImmutable the current time plus the given seconds, at second precision
     */
    public function expiresAt(int $ttlSeconds): \DateTimeImmutable
    {
        $now = $this->clock->now();

        return $now->setTimestamp($now->getTimestamp() + $ttlSeconds);
    }

    /**
     * A token stays valid through its expiry second.
     */
    public function isExpired(int $expiresAt): bool
    {
        return $expiresAt < $this->clock->now()->getTimestamp();
    }
}
