<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
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

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->contextPersister = new SalesChannelContextPersister(
            $this->connection,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CartPersister::class),
            'P1D' // 1 day expiration is the default value
        );
    }

    public function testLoadWithNoContextFoundReturnsEmptyArray(): void
    {
        // Simulate no context found in the database
        $this->connection->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

        $result = $this->contextPersister->load(SalesChannelContextService::getNewToken(), TestDefaults::SALES_CHANNEL, Uuid::randomHex());
        static::assertSame([], $result);
    }

    /**
     * @param array<string, string> $payload
     * @param ?array<string, string> $additionalPayload
     * @param array<string, string|bool> $expected
     */
    #[DataProvider('tokenExpirationDataProvider')]
    public function testLoadContextAgainstTokenExpiration(string $token, string $cartToken, ?string $customerId, \DateTimeImmutable $updatedAt, array $payload, ?array $additionalPayload, array $expected): void
    {
        $this->connection->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'token' => $token,
                'cart_token' => $cartToken,
                'payload' => \json_encode($payload, \JSON_THROW_ON_ERROR),
                'customer_id' => $customerId,
                'updated_at' => $updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'additional_payload' => $additionalPayload ? \json_encode($additionalPayload, \JSON_THROW_ON_ERROR) : null,
            ],
        ]);

        $result = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId);

        static::assertSame($expected, $result);
    }

    public static function tokenExpirationDataProvider(): \Generator
    {
        $token = SalesChannelContextService::getNewToken();
        $customerId = Uuid::randomHex();
        $updatedAt = new \DateTimeImmutable();
        // When we expire the token, we set it to 2 days ago, as there is 1 day expiration

        yield 'it keeps payload when customerId is provided and token is expired' => [
            'token' => $token,
            'cartToken' => 'abc',
            'customerId' => $customerId,
            'updatedAt' => $updatedAt->sub(new \DateInterval('P2D')),
            'payload' => ['a_key' => 'aValue'],
            'additionalPayload' => null,
            'expected' => ['a_key' => 'aValue', 'expired' => true, 'customerId' => $customerId, 'token' => $token, 'cartToken' => 'abc'],
        ];
        yield 'it withdraws payload when customerId is not provided and token is expired' => [
            'token' => $token,
            'cartToken' => 'abc',
            'customerId' => null,
            'updatedAt' => $updatedAt->sub(new \DateInterval('P2D')),
            'payload' => ['a_key' => 'aValue', 'anotherKey' => 'anotherValue'],
            'additionalPayload' => null,
            'expected' => ['expired' => true, 'token' => $token, 'cartToken' => 'abc'],
        ];

        yield 'it keeps payload when customerId is not provided and token is not expired' => [
            'token' => $token,
            'cartToken' => 'abc',
            'customerId' => null,
            'updatedAt' => $updatedAt,
            'payload' => ['a_key' => 'aValue'],
            'additionalPayload' => null,
            'expected' => ['a_key' => 'aValue', 'expired' => false, 'token' => $token, 'cartToken' => 'abc'],
        ];
        yield 'it keeps payload when customerId is provided and token is not expired' => [
            'token' => $token,
            'cartToken' => 'abc',
            'customerId' => $customerId,
            'updatedAt' => $updatedAt,
            'payload' => ['a_key' => 'aValue'],
            'additionalPayload' => null,
            'expected' => ['a_key' => 'aValue', 'expired' => false, 'customerId' => $customerId, 'token' => $token, 'cartToken' => 'abc'],
        ];
    }
}
