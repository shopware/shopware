<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Implements the server side of Device Bound Session Credentials (DBSC) for storefront sessions.
 *
 * @see https://w3c.github.io/webappsec-dbsc/
 *
 * @internal
 */
#[Package('framework')]
class DeviceBoundSessionService
{
    public const COOKIE_PREFIX = 'sw-dbsc-';

    private const REGISTRATION_CHALLENGE_LIFETIME = 300;

    /**
     * In-flight requests may still carry a cookie that was rotated or just expired.
     */
    private const COOKIE_GRACE_PERIOD = 60;

    public function __construct(
        private readonly DeviceBoundSessionStorage $storage,
        private readonly DeviceBoundSessionProofVerifier $verifier,
        private readonly ClockInterface $clock,
        private readonly string $secret,
        private readonly int $cookieLifetime,
        private readonly string $contextLifetime,
    ) {
    }

    public function getCookieExpiry(): \DateTimeImmutable
    {
        return $this->clock->now()->modify('+' . $this->cookieLifetime . ' seconds');
    }

    public function getCookieName(DeviceBoundSession $session): string
    {
        return self::COOKIE_PREFIX . substr($session->id, 0, 12);
    }

    public function findByContextToken(string $contextToken): ?DeviceBoundSession
    {
        return $this->storage->findByContextToken($contextToken);
    }

    public function findById(string $id): ?DeviceBoundSession
    {
        return $this->storage->findById($id);
    }

    public function delete(DeviceBoundSession $session): void
    {
        $this->storage->delete($session->id);
    }

    /**
     * Registration challenges are stateless, as they are sent with every response of a
     * logged-in customer whose browser has not registered (yet or at all).
     */
    public function createRegistrationChallenge(string $contextToken): string
    {
        $expiresAt = (string) ($this->clock->now()->getTimestamp() + self::REGISTRATION_CHALLENGE_LIFETIME);

        return $expiresAt . '.' . $this->sign($contextToken, $expiresAt);
    }

    /**
     * @return array{session: DeviceBoundSession, cookieValue: string}|null null when the proof is invalid or the context token is already bound
     */
    public function register(string $contextToken, string $proof): ?array
    {
        $registration = $this->verifier->verifyRegistration($proof);
        if ($registration === null || !$this->isValidRegistrationChallenge($contextToken, $registration['challenge'])) {
            return null;
        }

        $id = Uuid::randomHex();
        $cookieValue = $this->generateCookieValue();
        $now = $this->clock->now();

        $inserted = $this->storage->insert(
            id: $id,
            contextToken: $contextToken,
            publicKey: $registration['publicKey'],
            cookieHash: $this->hash($cookieValue),
            now: $now,
            expiresAt: $this->expiresAt($now),
        );

        // an existing binding must never be replaced, as that would let a stolen session cookie register its own key
        if (!$inserted) {
            return null;
        }

        $session = new DeviceBoundSession(
            id: $id,
            contextToken: $contextToken,
            publicKey: $registration['publicKey'],
            cookieHash: $this->hash($cookieValue),
            previousCookieHash: null,
            challenge: null,
            refreshedAt: $now,
        );

        return ['session' => $session, 'cookieValue' => $cookieValue];
    }

    public function createRefreshChallenge(DeviceBoundSession $session): string
    {
        $challenge = bin2hex(random_bytes(32));
        $this->storage->storeChallenge($session->id, $challenge);

        return $challenge;
    }

    /**
     * @return string|null the new cookie value, null when the proof is invalid or answers a stale challenge
     */
    public function refresh(DeviceBoundSession $session, string $proof): ?string
    {
        $challenge = $this->verifier->verifyRefresh($proof, $session->publicKey);
        if ($challenge === null || $session->challenge === null || !hash_equals($session->challenge, $challenge)) {
            return null;
        }

        $cookieValue = $this->generateCookieValue();
        $now = $this->clock->now();

        if (!$this->storage->rotateCookie($session->id, $challenge, $this->hash($cookieValue), $now, $this->expiresAt($now))) {
            return null;
        }

        return $cookieValue;
    }

    public function isCookieValid(DeviceBoundSession $session, ?string $cookieValue): bool
    {
        if ($cookieValue === null || $cookieValue === '') {
            return false;
        }

        $age = $this->clock->now()->getTimestamp() - $session->refreshedAt->getTimestamp();
        $hash = $this->hash($cookieValue);

        if (hash_equals($session->cookieHash, $hash)) {
            return $age <= $this->cookieLifetime + self::COOKIE_GRACE_PERIOD;
        }

        return $session->previousCookieHash !== null
            && hash_equals($session->previousCookieHash, $hash)
            && $age <= self::COOKIE_GRACE_PERIOD;
    }

    private function isValidRegistrationChallenge(string $contextToken, string $challenge): bool
    {
        $parts = explode('.', $challenge, 2);
        if (\count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }

        [$expiresAt, $signature] = $parts;

        return hash_equals($this->sign($contextToken, $expiresAt), $signature)
            && (int) $expiresAt >= $this->clock->now()->getTimestamp();
    }

    private function sign(string $contextToken, string $expiresAt): string
    {
        $mac = hash_hmac('sha256', 'device-bound-session|' . $contextToken . '|' . $expiresAt, $this->secret, true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    private function generateCookieValue(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hash(string $cookieValue): string
    {
        return Hasher::hash($cookieValue, 'sha256');
    }

    private function expiresAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->add(new \DateInterval($this->contextLifetime))->modify('+' . $this->cookieLifetime . ' seconds');
    }
}
