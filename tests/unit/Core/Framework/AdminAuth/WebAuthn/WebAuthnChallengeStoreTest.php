<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\WebAuthn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(WebAuthnChallengeStore::class)]
class WebAuthnChallengeStoreTest extends TestCase
{
    private const OPTIONS_JSON = '{"challenge":"abc","rpId":"example.com"}';

    public function testIssueAndConsumeRoundTrip(): void
    {
        $store = $this->createStore();

        $token = $store->issue(self::OPTIONS_JSON, WebAuthnChallengeStore::PURPOSE_LOGIN);

        static::assertSame(
            self::OPTIONS_JSON,
            $store->consume($token, WebAuthnChallengeStore::PURPOSE_LOGIN)
        );
    }

    public function testRegisterTokenIsBoundToTheUser(): void
    {
        $store = $this->createStore();

        $token = $store->issue(self::OPTIONS_JSON, WebAuthnChallengeStore::PURPOSE_REGISTER, 'user-a');

        static::assertSame(
            self::OPTIONS_JSON,
            $store->consume($token, WebAuthnChallengeStore::PURPOSE_REGISTER, 'user-a')
        );
        static::assertNull(
            $store->consume($token, WebAuthnChallengeStore::PURPOSE_REGISTER, 'user-b'),
            'a registration token must not be consumable for another user'
        );
        static::assertNull(
            $store->consume($token, WebAuthnChallengeStore::PURPOSE_REGISTER),
            'a user-bound token must not be consumable without the user binding'
        );
    }

    public function testPurposeMismatchIsRejected(): void
    {
        $store = $this->createStore();

        $token = $store->issue(self::OPTIONS_JSON, WebAuthnChallengeStore::PURPOSE_LOGIN);

        static::assertNull($store->consume($token, WebAuthnChallengeStore::PURPOSE_REGISTER));
    }

    public function testTamperedTokenIsRejected(): void
    {
        $store = $this->createStore();

        $token = $store->issue(self::OPTIONS_JSON, WebAuthnChallengeStore::PURPOSE_LOGIN);
        [$payload, $signature] = explode('.', $token, 2);

        $forgedPayload = base64_encode(json_encode([
            'options' => '{"challenge":"attacker-chosen"}',
            'purpose' => WebAuthnChallengeStore::PURPOSE_LOGIN,
            'userId' => null,
            'exp' => \PHP_INT_MAX,
        ], \JSON_THROW_ON_ERROR));

        static::assertNull($store->consume($forgedPayload . '.' . $signature, WebAuthnChallengeStore::PURPOSE_LOGIN));
    }

    public function testTokenSignedWithAnotherSecretIsRejected(): void
    {
        $token = $this->createStore('secret-one')->issue(self::OPTIONS_JSON, WebAuthnChallengeStore::PURPOSE_LOGIN);

        static::assertNull($this->createStore('secret-two')->consume($token, WebAuthnChallengeStore::PURPOSE_LOGIN));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $clock = new MockClock('2026-07-02 12:00:00');
        $store = $this->createStore(clock: $clock);

        $token = $store->issue(self::OPTIONS_JSON, WebAuthnChallengeStore::PURPOSE_LOGIN);

        $clock->modify('+6 minutes');

        static::assertNull($store->consume($token, WebAuthnChallengeStore::PURPOSE_LOGIN));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function malformedTokenProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'no separator' => ['garbage'];
        yield 'too many separators' => ['a.b.c'];
        yield 'invalid base64 payload' => ['%%%.signature'];
    }

    #[DataProvider('malformedTokenProvider')]
    public function testMalformedTokenIsRejected(?string $token): void
    {
        static::assertNull($this->createStore()->consume($token, WebAuthnChallengeStore::PURPOSE_LOGIN));
    }

    private function createStore(string $secret = 'test-app-secret', ?MockClock $clock = null): WebAuthnChallengeStore
    {
        return new WebAuthnChallengeStore($secret, $clock ?? new MockClock());
    }
}
