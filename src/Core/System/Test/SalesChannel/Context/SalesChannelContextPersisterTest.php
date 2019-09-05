<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;

class SalesChannelContextPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

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
        $this->contextPersister = new SalesChannelContextPersister($this->connection);
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

    public function testLoadNotExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        static::assertSame([], $this->contextPersister->load($token));
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
