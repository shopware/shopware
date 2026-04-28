<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelContextPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    private SalesChannelContextPersister $contextPersister;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new SalesChannelContextPersister($this->connection, $eventDispatcher, static::getContainer()->get(CartPersister::class));
    }

    public function testLoad(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $expected = [
            'key' => 'value',
            'token' => $token,
            'expired' => false,
            'cartToken' => 'cart-token',
        ];
        $id = Uuid::randomBytes();

        $this->connection->insert('sales_channel_context', [
            'id' => $id,
            'cart_token' => 'cart-token',
            'payload' => json_encode($expected),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ]);

        $this->connection->insert('sales_channel_context_token', [
            'token' => $token,
            'sales_channel_context_id' => $id,
        ]);

        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL));
    }

    public function testLoadByCustomerId(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $customerId = $this->createCustomer();
        $this->contextPersister->save($token, [], TestDefaults::SALES_CHANNEL, $customerId);

        static::assertNotEmpty($result = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId));
        static::assertArrayHasKey('token', $result);
        static::assertSame($token, $result['token']);
    }

    public function testLoadNotExisting(): void
    {
        $token = SalesChannelContextService::getNewToken();

        static::assertSame([], $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL));
    }

    public function testLoadCustomerNotExisting(): void
    {
        $customerId = Uuid::randomHex();
        $token = SalesChannelContextService::getNewToken();

        static::assertSame([], $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId));
    }

    public function testLoadKeepsPayloadWhenTokenExpiresAndCustomerIdIsProvided(): void
    {
        $token = SalesChannelContextService::getNewToken();

        $expected = $payload = [
            'key' => 'value',
            'anotherKey' => 'anotherValue',
            'expired' => false,
            'token' => $token,
            'cartToken' => $token,
        ];

        $this->contextPersister->save($token, $payload, TestDefaults::SALES_CHANNEL);

        $this->makeTokenAge($token, 2);

        // Load with customerId should keep the payload and just mark it as expired
        $expected['expired'] = true;
        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, Uuid::randomHex()));
    }

    public function testLoadWithdrawPayloadWhenTokenExpiresAndCustomerIdIsNotProvided(): void
    {
        $token = SalesChannelContextService::getNewToken();

        $payload = [
            'key' => 'value',
            'anotherKey' => 'anotherValue',
            'token' => $token,
            'cartToken' => $token,
            'expired' => false,
        ];

        $this->contextPersister->save($token, $payload, TestDefaults::SALES_CHANNEL);

        $this->makeTokenAge($token, 2);

        // Everything except 'expired', 'token' & 'cartToken' should be removed when loading without customerId
        static::assertSame(['expired' => true, 'token' => $token, 'cartToken' => $token], $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL));
    }

    public function testSaveWithoutExistingContext(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $expected = [
            'key' => 'value',
            'expired' => false,
            'token' => $token,
            'cartToken' => $token, // Should be the same token as it uses it as a fallback
        ];

        $this->contextPersister->save($token, $expected, TestDefaults::SALES_CHANNEL);

        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL));
    }

    public function testSaveNewCustomerContextWithoutExistingCustomer(): void
    {
        $customerId = $this->createCustomer();
        $token = SalesChannelContextService::getNewToken();
        $expected = [
            'key' => 'value',
            'token' => $token,
            'expired' => false,
            'customerId' => $customerId,
            'cartToken' => $token,
        ];

        $this->contextPersister->save($token, $expected, TestDefaults::SALES_CHANNEL, $customerId);

        $result = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId);

        static::assertNotEmpty($result);

        static::assertEquals($expected, $result);
        static::assertArrayHasKey('token', $result);
        static::assertSame($token, $result['token']);
    }

    public function testSaveMergesWithExisting(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $id = Uuid::randomBytes();

        $this->connection->insert('sales_channel_context', [
            'id' => $id,
            'cart_token' => 'cart-token',
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ]);

        $this->connection->insert('sales_channel_context_token', [
            'token' => $token,
            'sales_channel_context_id' => $id,
        ]);

        $this->contextPersister->save(
            $token,
            [
                'second' => 'overwritten',
                'third' => 'third test',
            ],
            TestDefaults::SALES_CHANNEL
        );

        $expected = [
            'cartToken' => $token, // Without the feature flag this will be the same as 'token'
            'expired' => false,
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ];

        $actual = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL);
        ksort($actual);

        static::assertSame($expected, $actual);
    }

    public function testSaveCustomerContextMergesWithExisting(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $id = Uuid::randomBytes();
        $customerId = $this->createCustomer();

        $this->connection->insert('sales_channel_context', [
            'id' => $id,
            'cart_token' => 'cart-token',
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'customer_id' => Uuid::fromHexToBytes($customerId),
        ]);

        $this->connection->insert('sales_channel_context_token', [
            'token' => $token,
            'sales_channel_context_id' => $id,
        ]);

        $this->contextPersister->save($token, [
            'second' => 'overwritten',
            'third' => 'third test',
        ], TestDefaults::SALES_CHANNEL, $customerId);

        $expected = [
            'cartToken' => $token, // Without the feature flag this will be the same as 'token'
            'customerId' => $customerId,
            'expired' => false,
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ];
        $actual = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId);
        ksort($actual);

        static::assertSame($expected, $actual);
    }

    public function testLoadSameCustomerOnDifferentSalesChannel(): void
    {
        $customerId = $this->createCustomer();

        $salesChannel1 = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'domains' => [
                [
                    'url' => 'http://test.en',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $salesChannel2 = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $token1 = Uuid::randomHex();
        $token2 = Uuid::randomHex();

        $this->contextPersister->save($token1, ['languageId' => '123'], $salesChannel1['id'], $customerId);
        $this->contextPersister->save($token2, ['languageId' => '456'], $salesChannel2['id'], $customerId);

        // Without saved context sales channel (different sales channel id)
        static::assertEmpty($this->contextPersister->load($token1, TestDefaults::SALES_CHANNEL, $customerId));
        static::assertEmpty($this->contextPersister->load($token2, TestDefaults::SALES_CHANNEL, $customerId));

        $contextPayload1 = $this->contextPersister->load(Uuid::randomHex(), $salesChannel1['id'], $customerId);
        static::assertNotEmpty($contextPayload1);
        static::assertArrayHasKey('token', $contextPayload1);
        static::assertSame($token1, $contextPayload1['token']);
        static::assertArrayHasKey('languageId', $contextPayload1);
        static::assertSame('123', $contextPayload1['languageId']);

        $contextPayload2 = $this->contextPersister->load(Uuid::randomHex(), $salesChannel2['id'], $customerId);

        static::assertNotEmpty($contextPayload2);
        static::assertArrayHasKey('token', $contextPayload2);
        static::assertSame($token2, $contextPayload2['token']);
        static::assertArrayHasKey('languageId', $contextPayload2);
        static::assertSame('456', $contextPayload2['languageId']);
    }

    public function testReplaceWithoutExistingContext(): void
    {
        $token = SalesChannelContextService::getNewToken();

        $context = Generator::generateSalesChannelContext(overrides: ['customer' => null]);
        $newToken = $this->contextPersister->replace($token, $context);

        static::assertTrue($this->contextExists($newToken));
        static::assertFalse($this->contextExists($token));
    }

    public function testSaveReplaceWithExistingContext(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $id = Uuid::randomBytes();

        $this->connection->insert('sales_channel_context', [
            'id' => $id,
            'cart_token' => 'cart-token',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ]);

        $this->connection->insert('sales_channel_context_token', [
            'token' => $token,
            'sales_channel_context_id' => $id,
        ]);

        $context = Generator::generateSalesChannelContext(overrides: ['customer' => null]);
        $newToken = $this->contextPersister->replace($token, $context);

        static::assertTrue($this->contextExists($newToken));
        static::assertFalse($this->contextExists($token));
    }

    public function testReplaceUpdatesCartTokenToo(): void
    {
        $token = SalesChannelContextService::getNewToken();

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, TestDefaults::SALES_CHANNEL);

        $cart = new Cart($token);
        $cart->addLineItems(new LineItemCollection([new LineItem('test', 'test', Uuid::randomHex(), 10)]));
        static::getContainer()->get(CartPersister::class)->save($cart, $context);

        static::assertTrue($this->cartExists($token));

        $newToken = $this->contextPersister->replace($token, $context);

        static::assertTrue($this->cartExists($newToken));
        static::assertFalse($this->cartExists($token));
    }

    public function testCustomerIdColumnIsBeingUsed(): void
    {
        $customerId = $this->createCustomer();
        $token = SalesChannelContextService::getNewToken();
        $id = Uuid::randomBytes();

        $this->connection->insert('sales_channel_context', [
            'id' => $id,
            'cart_token' => 'cart-token',
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ]);

        $this->connection->insert('sales_channel_context_token', [
            'token' => $token,
            'sales_channel_context_id' => $id,
        ]);

        $this->contextPersister->revokeAllCustomerTokens($customerId);

        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_context_token WHERE token = ?', [$token]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_context WHERE customer_id = ?', [Uuid::fromHexToBytes($customerId)]));
    }

    public static function tokenExpiringDataProvider(): \Generator
    {
        yield [0, 'P2D', false];
        yield [1, 'P2D', false];
        yield [3, 'P2D', true];
        yield [0, 'P1D', false];
        yield [2, 'P1D', true];
    }

    #[DataProvider('tokenExpiringDataProvider')]
    public function testTokenExpiring(int $tokenAgeInDays, string $lifeTimeInterval, bool $expectedExpired): void
    {
        $persister = new SalesChannelContextPersister(
            $this->connection,
            $this->createMock(EventDispatcher::class),
            static::getContainer()->get(CartPersister::class),
            $lifeTimeInterval
        );
        $token = SalesChannelContextService::getNewToken();

        $customerId = $this->createCustomer();
        $persister->save($token, [], TestDefaults::SALES_CHANNEL, $customerId);

        $this->makeTokenAge($token, $tokenAgeInDays);

        $result = $persister->load($token, TestDefaults::SALES_CHANNEL, $customerId);

        static::assertArrayHasKey('expired', $result);
        static::assertSame($result['expired'], $expectedExpired);
    }

    #[DataProvider('revokeTokensTestDataProvider')]
    public function testRevokeTokens(string $token, ?string $preserveToken): void
    {
        $customerId = $this->createCustomer();
        $this->contextPersister->save($token, [], TestDefaults::SALES_CHANNEL, $customerId);

        // check token is valid here
        static::assertNotEmpty($result = $this->contextPersister->load($token, TestDefaults::SALES_CHANNEL, $customerId));
        static::assertArrayHasKey('token', $result);
        static::assertSame($token, $result['token']);

        if ($preserveToken) {
            $this->contextPersister->revokeAllCustomerTokens($customerId, $preserveToken);
        } else {
            $this->contextPersister->revokeAllCustomerTokens($customerId);
        }

        if ($preserveToken) {
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_context_token WHERE token = ?', [$token]));
        } else {
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_context_token WHERE token = ?', [$token]));
        }

        // The context should still exist
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_context WHERE customer_id = ?', [Uuid::fromHexToBytes($customerId)]));
    }

    public static function revokeTokensTestDataProvider(): \Generator
    {
        yield [SalesChannelContextService::getNewToken(), ''];
        yield [$token = SalesChannelContextService::getNewToken(), $token];
    }

    private function cartExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM cart WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    private function contextExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM sales_channel_context_token WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    /**
     * Changes the age of a token by updating the updated_at field in the database.
     */
    private function makeTokenAge(string $token, int $tokenAgeInDays): void
    {
        if ($tokenAgeInDays !== 0) {
            $this->connection->executeStatement(
                'UPDATE sales_channel_context_token
                SET updated_at = DATE_ADD(updated_at, INTERVAL :intervalInDays DAY)
                WHERE token = :token',
                ['intervalInDays' => -$tokenAgeInDays, 'token' => $token]
            );
        }
    }
}
