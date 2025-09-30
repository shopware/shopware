<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelContextPersister::class)]
class SalesChannelContextPersisterTest extends TestCase
{
    private SalesChannelContextPersister $contextPersister;

    private MockObject&Result $statement;

    protected function setUp(): void
    {
        $this->statement = $this->createMock(Result::class);

        $connection = $this->createMock(Connection::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($this->statement);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->contextPersister = new SalesChannelContextPersister(
            $connection,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CartPersister::class),
            'P1D' // 1 day expiration is the default value
        );
    }

    public function testLoadWithNoContextFoundReturnsEmptyArray(): void
    {
        // Simulate no context found in the database
        $this->statement->method('fetchAllAssociative')->willReturn([]);

        $result = $this->contextPersister->load(Random::getAlphanumericString(32), TestDefaults::SALES_CHANNEL, Uuid::randomHex());
        static::assertSame([], $result);
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, string|bool> $expected
     */
    #[DataProvider('tokenExpirationDataProvider')]
    public function testLoadContextAgainstTokenExpiration(string $token, ?string $customerId, \DateTimeImmutable $updatedAt, array $payload, array $expected): void
    {
        $this->statement->method('fetchAllAssociative')->willReturn([
            [
                'updated_at' => $updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'payload' => \json_encode($payload, \JSON_THROW_ON_ERROR),
                'token' => $token,
            ],
        ]);

        $result = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId);

        static::assertSame($expected, $result);
    }

    public static function tokenExpirationDataProvider(): \Generator
    {
        $token = Random::getAlphanumericString(32);
        $customerId = Uuid::randomHex();
        $updatedAt = new \DateTimeImmutable();
        // When we expire the token, we set it to 2 days ago, as there is 1 day expiration

        yield 'it keeps payload when customerId is provided and token is expired' => [
            'token' => $token,
            'customerId' => $customerId,
            'updatedAt' => $updatedAt->sub(new \DateInterval('P2D')),
            'payload' => ['a_key' => 'aValue'],
            'expected' => ['a_key' => 'aValue', 'expired' => true, 'token' => $token],
        ];
        yield 'it withdraws payload when customerId is not provided and token is expired' => [
            'token' => $token,
            'customerId' => null,
            'updatedAt' => $updatedAt->sub(new \DateInterval('P2D')),
            'payload' => ['a_key' => 'aValue', 'anotherKey' => 'anotherValue'],
            'expected' => ['expired' => true, 'token' => $token],
        ];

        yield 'it keeps payload when customerId is not provided and token is not expired' => [
            'token' => $token,
            'customerId' => null,
            'updatedAt' => $updatedAt,
            'payload' => ['a_key' => 'aValue'],
            'expected' => ['a_key' => 'aValue', 'expired' => false, 'token' => $token],
        ];
        yield 'it keeps payload when customerId is provided and token is not expired' => [
            'token' => $token,
            'customerId' => $customerId,
            'updatedAt' => $updatedAt,
            'payload' => ['a_key' => 'aValue'],
            'expected' => ['a_key' => 'aValue', 'expired' => false, 'token' => $token],
        ];
    }
}
