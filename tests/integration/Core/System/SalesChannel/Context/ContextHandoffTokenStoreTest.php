<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;

/**
 * @internal
 */
#[Package('framework')]
class ContextHandoffTokenStoreTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private ContextHandoffTokenStore $store;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->store = static::getContainer()->get(ContextHandoffTokenStore::class);
    }

    public function testStoredContextTokenIsReturnedOnce(): void
    {
        $jti = Uuid::randomHex();
        $this->store->store($jti, 'the-context-token', new \DateTimeImmutable('+60 seconds'));

        static::assertSame('the-context-token', $this->store->consume($jti));
        static::assertNull($this->store->consume($jti));
    }

    public function testConsumingRemovesTheContextTokenFromTheDatabase(): void
    {
        $jti = Uuid::randomHex();
        $this->store->store($jti, 'the-context-token', new \DateTimeImmutable('+60 seconds'));

        static::assertSame('the-context-token', $this->store->consume($jti));
        static::assertFalse($this->connection->fetchOne(
            'SELECT context_token FROM context_handoff_token WHERE token = :token',
            ['token' => $jti]
        ));
    }

    public function testConsumeReturnsNullForAnUnknownJti(): void
    {
        static::assertNull($this->store->consume(Uuid::randomHex()));
    }

    public function testExpiredEntryIsNotReturned(): void
    {
        $jti = Uuid::randomHex();
        $this->store->store($jti, 'the-context-token', new \DateTimeImmutable('-1 second'));

        static::assertNull($this->store->consume($jti));
    }

    public function testEntriesOfDifferentTokensDoNotInterfere(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $this->store->store($first, 'first-context-token', new \DateTimeImmutable('+60 seconds'));
        $this->store->store($second, 'second-context-token', new \DateTimeImmutable('+60 seconds'));

        static::assertSame('first-context-token', $this->store->consume($first));
        static::assertSame('second-context-token', $this->store->consume($second));
    }

    public function testDeleteExpiredOnlyRemovesExpiredEntries(): void
    {
        $expired = Uuid::randomHex();
        $valid = Uuid::randomHex();

        $this->store->store($expired, 'expired-context-token', new \DateTimeImmutable('-1 second'));
        $this->store->store($valid, 'valid-context-token', new \DateTimeImmutable('+60 seconds'));

        $this->store->deleteExpired();

        static::assertFalse($this->connection->fetchOne(
            'SELECT context_token FROM context_handoff_token WHERE token = :token',
            ['token' => $expired]
        ));
        static::assertSame('valid-context-token', $this->store->consume($valid));
    }
}
