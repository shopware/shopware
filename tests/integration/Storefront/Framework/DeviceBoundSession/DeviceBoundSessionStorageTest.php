<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\DeviceBoundSession;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionStorage;

/**
 * @internal
 */
#[Package('framework')]
class DeviceBoundSessionStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const PUBLIC_KEY = ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'x', 'y' => 'y'];

    private DeviceBoundSessionStorage $storage;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->storage = static::getContainer()->get(DeviceBoundSessionStorage::class);
        $this->now = new \DateTimeImmutable('2026-09-23 10:00:00.000');
    }

    public function testInsertedSessionCanBeFoundByContextTokenAndId(): void
    {
        $id = Uuid::randomHex();

        static::assertTrue($this->insert($id, 'context-token'));

        $session = $this->storage->findByContextToken('context-token');
        static::assertNotNull($session);
        static::assertSame($id, $session->id);
        static::assertSame(self::PUBLIC_KEY, $session->publicKey);
        static::assertSame('cookie-hash', $session->cookieHash);
        static::assertNull($session->previousCookieHash);
        static::assertNull($session->challenge);
        static::assertEquals($this->now, $session->refreshedAt);

        static::assertEquals($session, $this->storage->findById($id));
    }

    public function testFindByInvalidIdReturnsNothing(): void
    {
        static::assertNull($this->storage->findById('not-a-uuid'));
    }

    public function testBoundContextTokenCannotBeBoundAgain(): void
    {
        $id = Uuid::randomHex();
        $this->insert($id, 'context-token');

        static::assertFalse($this->insert(Uuid::randomHex(), 'context-token'));
        static::assertSame($id, $this->storage->findByContextToken('context-token')?->id);
    }

    public function testInsertRemovesExpiredSessions(): void
    {
        $this->insert(Uuid::randomHex(), 'expired-token', expiresAt: $this->now->modify('-1 second'));

        $this->insert(Uuid::randomHex(), 'context-token');

        static::assertNull($this->storage->findByContextToken('expired-token'));
    }

    public function testRotationConsumesTheChallengeOnce(): void
    {
        $id = Uuid::randomHex();
        $this->insert($id, 'context-token');
        $this->storage->storeChallenge($id, 'challenge');

        $later = $this->now->modify('+10 minutes');
        static::assertTrue($this->storage->rotateCookie($id, 'challenge', 'new-cookie-hash', $later, $later->modify('+1 day')));
        static::assertFalse($this->storage->rotateCookie($id, 'challenge', 'other-cookie-hash', $later, $later->modify('+1 day')));

        $session = $this->storage->findById($id);
        static::assertNotNull($session);
        static::assertSame('new-cookie-hash', $session->cookieHash);
        static::assertSame('cookie-hash', $session->previousCookieHash);
        static::assertNull($session->challenge);
        static::assertEquals($later, $session->refreshedAt);
    }

    public function testRotationWithReplacedChallengeFails(): void
    {
        $id = Uuid::randomHex();
        $this->insert($id, 'context-token');
        $this->storage->storeChallenge($id, 'first');
        $this->storage->storeChallenge($id, 'second');

        static::assertFalse($this->storage->rotateCookie($id, 'first', 'new-cookie-hash', $this->now, $this->now->modify('+1 day')));
    }

    public function testSessionCanBeDeleted(): void
    {
        $id = Uuid::randomHex();
        $this->insert($id, 'context-token');
        $this->insert(Uuid::randomHex(), 'other-context-token');

        $this->storage->delete($id);

        static::assertNull($this->storage->findById($id));
        static::assertNotNull($this->storage->findByContextToken('other-context-token'));
    }

    private function insert(string $id, string $contextToken, ?\DateTimeImmutable $expiresAt = null): bool
    {
        return $this->storage->insert(
            id: $id,
            contextToken: $contextToken,
            publicKey: self::PUBLIC_KEY,
            cookieHash: 'cookie-hash',
            now: $this->now,
            expiresAt: $expiresAt ?? $this->now->modify('+1 day'),
        );
    }
}
