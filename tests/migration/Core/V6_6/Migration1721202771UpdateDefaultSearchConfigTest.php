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
use Shopware\Core\Migration\V6_6\Migration1721202771UpdateDefaultSearchConfig;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1721202771UpdateDefaultSearchConfig::class)]
class Migration1721202771UpdateDefaultSearchConfigTest extends TestCase
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

        $migration = new Migration1721202771UpdateDefaultSearchConfig();
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

        yield 'Add path to media module configs' => [
            [
                self::record($ids->create('user'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('media', ['title']),
                ]),
            ],
            [
                $ids->get('user') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('media', ['title', 'path']),
                ],
            ],
        ];

        yield 'Also works with multiple records' => [
            [
                self::record($ids->create('user-1'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('media', ['title']),
                ]),
                self::record($ids->create('user-2'), [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('media', ['title', 'fileName']),
                ]),
            ],
            [
                $ids->get('user-1') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('media', ['title', 'path']),
                ],
                $ids->get('user-2') => [
                    self::module('product', ['name', 'lineItems']),
                    self::module('customer', ['name', 'lineItems']),
                    self::module('media', ['title', 'fileName', 'path']),
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
            'created_at' => '2024-01-01 00:00:00',
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
                $mapped[$value] = ['_searchable' => true, '_score' => 500];

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
