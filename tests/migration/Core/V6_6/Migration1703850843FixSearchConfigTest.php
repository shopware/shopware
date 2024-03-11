<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1703850843FixSearchConfig;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1703850843FixSearchConfig::class)]
class Migration1703850843FixSearchConfigTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0;');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->executeStatement('DELETE FROM `user_config` WHERE `key` = :key', ['key' => 'search.preferences']);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expected
     */
    #[DataProvider('migrationProvider')]
    public function testMigration(array $input, array $expected): void
    {
        $queue = new MultiInsertQueryQueue($this->connection);

        foreach ($input as $item) {
            $queue->addInsert('user_config', $item);
        }

        $queue->execute();

        $migration = new Migration1703850843FixSearchConfig();
        $migration->update($this->connection);

        $result = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `value` FROM `user_config` WHERE `key` = :key',
            ['key' => 'search.preferences']
        );

        static::assertCount(\count($expected), $result);

        foreach ($expected as $id => $item) {
            static::assertArrayHasKey($id, $result);

            if ($item === null) {
                static::assertNull($result[$id]);

                continue;
            }

            static::assertEquals($item, json_decode($result[$id], true));
        }
    }

    public static function migrationProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Missing config' => [
            [],
            [],
        ];

        yield 'Config with null value' => [
            [
                self::record($ids->create('user'), null),
            ],
            [
                $ids->get('user') => null,
            ],
        ];

        yield 'Dont touch none order module configs' => [
            [
                self::record($ids->create('user'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'lineItems']),
                ]),
            ],
            [
                $ids->get('user') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name']),
                ],
            ],
        ];

        yield 'Remove deliveries.shippingOrderAddress key' => [
            [
                self::record($ids->create('user'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'deliveries' => ['shippingOrderAddress' => ['street', 'zipcode']]]),
                ]),
            ],
            [
                $ids->get('user') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name']),
                ],
            ],
        ];

        yield 'Kee deliveries when not empty' => [
            [
                self::record($ids->create('user'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'deliveries' => ['trackingCodes', 'shippingOrderAddress' => ['street', 'zipcode']]]),
                ]),
            ],
            [
                $ids->get('user') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'deliveries' => ['trackingCodes']]),
                ],
            ],
        ];

        yield 'Remove orderCustomer.customer key' => [
            [
                self::record($ids->create('user'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'orderCustomer' => ['customer' => ['firstName', 'lastName']]]),
                ]),
            ],
            [
                $ids->get('user') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name']),
                ],
            ],
        ];

        yield 'Also works with multiple records' => [
            [
                self::record($ids->create('user-1'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'orderCustomer' => ['customer' => ['firstName', 'lastName']]]),
                ]),
                self::record($ids->create('user-2'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'deliveries' => ['trackingCodes', 'shippingOrderAddress' => ['street', 'zipcode']]]),
                ]),
            ],
            [
                $ids->get('user-1') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name']),
                ],
                $ids->get('user-2') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('order', ['name', 'deliveries' => ['trackingCodes']]),
                ],
            ],
        ];
    }

    /**
     * @param array<mixed>|null $modules
     *
     * @return array<string, mixed>
     */
    private static function record(string $id, ?array $modules, string $key = 'search.preferences'): array
    {
        return [
            'id' => Uuid::fromHexToBytes($id),
            'user_id' => Uuid::fromHexToBytes($id),
            'key' => $key,
            'value' => $modules ? json_encode($modules) : null,
            'created_at' => '2021-01-01 00:00:00',
        ];
    }

    /**
     * @param array<mixed> $fields
     *
     * @return array<mixed>
     */
    private static function module(string $key, array $fields): array
    {
        return iterator_to_array(self::resolve($key, $fields));
    }

    /**
     * @param array<mixed> $fields
     */
    private static function resolve(string $key, array $fields): \Generator
    {
        $mapped = [];
        foreach ($fields as $i => $value) {
            if (!\is_array($value)) {
                $mapped[$value] = ['score' => 100];

                continue;
            }

            $nested = self::resolve($i, $value);

            foreach (iterator_to_array($nested) as $x => $item) {
                $mapped[$x] = $item;
            }
        }

        yield $key => $mapped;
    }
}
