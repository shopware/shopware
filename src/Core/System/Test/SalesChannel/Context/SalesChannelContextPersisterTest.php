<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SalesChannelContextPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new SalesChannelContextPersister($this->connection, $eventDispatcher);
    }

    public function testLoad(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
        ];

        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => json_encode($expected),
        ]);

        static::assertSame($expected, $this->contextPersister->load($token));
    }

    public function testLoadByCustomerId(): void
    {
        $token = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $this->contextPersister->save($token, [], Defaults::SALES_CHANNEL, $customerId);

        static::assertNotEmpty($result = $this->contextPersister->load($token, Defaults::SALES_CHANNEL, $customerId));
        static::assertEquals($token, $result['token']);
    }

    public function testLoadNotExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        static::assertSame([], $this->contextPersister->load($token));
    }

    public function testLoadCustomerNotExisting(): void
    {
        $customerId = Uuid::randomHex();
        $token = Random::getAlphanumericString(32);

        static::assertSame([], $this->contextPersister->load($token, Defaults::SALES_CHANNEL, $customerId));
    }

    public function testSaveWithoutExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
        ];

        $this->contextPersister->save($token, $expected);

        static::assertSame($expected, $this->contextPersister->load($token));
    }

    public function testSaveNewCustomerContextWithoutExistingCustomer(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
            'token' => $token,
        ];

        $customerId = $this->createCustomer();

        $this->contextPersister->save($token, $expected, Defaults::SALES_CHANNEL, $customerId);

        $result = $this->contextPersister->load($token, Defaults::SALES_CHANNEL, $customerId);

        static::assertNotEmpty($result);

        static::assertEquals($expected, $result);
        static::assertEquals($token, $result['token']);
    }

    public function testSaveMergesWithExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
        ]);

        $this->contextPersister->save($token, [
            'second' => 'overwritten',
            'third' => 'third test',
        ]);

        $expected = [
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
        ];

        $actual = $this->contextPersister->load($token);
        ksort($actual);

        static::assertSame($expected, $actual);
    }

    public function testSaveCustomerContextMergesWithExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        $customerId = $this->createCustomer();

        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'customer_id' => Uuid::fromHexToBytes($customerId),
        ]);

        $this->contextPersister->save($token, [
            'second' => 'overwritten',
            'third' => 'third test',
        ], Defaults::SALES_CHANNEL, $customerId);

        $expected = [
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ];
        $actual = $this->contextPersister->load($token, Defaults::SALES_CHANNEL, $customerId);
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

        $this->contextPersister->save($token1, [], $salesChannel1['id'], $customerId);
        $this->contextPersister->save($token2, [], $salesChannel2['id'], $customerId);

        // Without saved context sales channel
        static::assertEmpty($this->contextPersister->load($token1, Defaults::SALES_CHANNEL, $customerId));
        static::assertEmpty($this->contextPersister->load($token2, Defaults::SALES_CHANNEL, $customerId));

        $contextPayload1 = $this->contextPersister->load(Uuid::randomHex(), $salesChannel1['id'], $customerId);
        static::assertNotEmpty($contextPayload1);
        static::assertEquals($token1, $contextPayload1['token']);

        $contextPayload2 = $this->contextPersister->load(Uuid::randomHex(), $salesChannel2['id'], $customerId);

        static::assertNotEmpty($contextPayload2);
        static::assertEquals($token2, $contextPayload2['token']);
    }

    public function testReplaceWithoutExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);

        $newToken = $this->contextPersister->replace($token);

        static::assertTrue($this->contextExists($newToken));
        static::assertFalse($this->contextExists($token));
    }

    public function testSaveReplaceWithExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);

        $this->connection->insert('sales_channel_api_context', [
            'token' => $token,
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
        ]);

        $newToken = $this->contextPersister->replace($token);

        static::assertTrue($this->contextExists($newToken));
        static::assertFalse($this->contextExists($token));
    }

    public function testRepalceUpdatesCartTokenToo(): void
    {
        $token = Random::getAlphanumericString(32);

        $this->connection->insert('cart', [
            'token' => $token,
            'name' => 'test',
            'cart' => 'test',
            'price' => 19.5,
            'line_item_count' => 3,
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'shipping_method_id' => Uuid::fromHexToBytes($this->getValidShippingMethodId()),
            'payment_method_id' => Uuid::fromHexToBytes($this->getValidPaymentMethodId()),
            'country_id' => Uuid::fromHexToBytes($this->getValidCountryId()),
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertTrue($this->cartExists($token));

        $newToken = $this->contextPersister->replace($token);

        static::assertTrue($this->cartExists($newToken));
        static::assertFalse($this->cartExists($token));
    }

    private function createCustomer(): string
    {
        $customerRepository = $this->getContainer()->get('customer.repository');
        $salutationId = $this->getValidSalutationId();

        $customerId = Uuid::randomHex();
        $billingAddress = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'countryId' => $this->getValidCountryId(),
        ];

        $shippingAddress = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'countryId' => $this->getValidCountryId(),
        ];

        $customer = [
            'id' => $customerId,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultShippingAddress' => $shippingAddress,
            'defaultBillingAddress' => $billingAddress,
            'defaultPaymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => '$password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $salutationId,
            'customerNumber' => '12345',
        ];

        $customerRepository->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function cartExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM cart WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchColumn();

        return $result > 0;
    }

    private function contextExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM sales_channel_api_context WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchColumn();

        return $result > 0;
    }
}
