<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1594104496CashRoundingTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @dataProvider currencyInsertTriggerProvider
     *
     * @param array{decimals: int, roundForNet: bool, interval: float} $expected
     * @param array{decimals: int, roundForNet: bool, interval: float}|null $initial
     */
    public function testCurrencyInsertTrigger(int $decimals, array $expected, ?array $initial = null): void
    {
        if ($this->getTriggerInfo('currency_cash_rounding_insert') === false) {
            static::markTestSkipped('trigger "currency_cash_rounding_insert" does not exist');
        }

        $id = Uuid::randomBytes();
        $data = [
            'id' => $id,
            'decimal_precision' => $decimals,
            'factor' => 1,
            'symbol' => 'A',
            'iso_code' => 'de',
            'created_at' => (new \DateTime())->format('Y-m-d'),
        ];

        if ($initial) {
            $data['item_rounding'] = json_encode($initial, \JSON_THROW_ON_ERROR);
            $data['total_rounding'] = json_encode($initial, \JSON_THROW_ON_ERROR);
        }

        $this->connection->insert('currency', $data);

        $record = $this->connection->fetchAssociative(
            'SELECT item_rounding, total_rounding FROM currency WHERE id = :id',
            ['id' => $id]
        );

        static::assertIsArray($record);
        static::assertEquals($expected, json_decode((string) $record['item_rounding'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertEquals($expected, json_decode((string) $record['total_rounding'], true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider currencyUpdateTriggerProvider
     *
     * @param array{decimals: int, roundForNet: bool, interval: float}|null  $rounding
     * @param array{decimals: int, roundForNet: bool, interval: float} $expected
     */
    public function testCurrencyUpdateTrigger(?int $decimals, ?array $rounding, array $expected): void
    {
        if ($this->getTriggerInfo('currency_cash_rounding_update') === false) {
            static::markTestSkipped('trigger "currency_cash_rounding_update" does not exist');
        }

        $id = Uuid::randomBytes();
        $data = [
            'id' => $id,
            'decimal_precision' => 2,
            'item_rounding' => json_encode(['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01]),
            'total_rounding' => json_encode(['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01]),
            'factor' => 1,
            'symbol' => 'A',
            'iso_code' => 'de',
            'created_at' => (new \DateTime())->format('Y-m-d'),
        ];

        $this->connection->insert('currency', $data);

        if ($decimals) {
            $this->connection->executeStatement('UPDATE currency SET decimal_precision = :decimals', ['decimals' => $decimals]);
        } else {
            $this->connection->executeStatement('UPDATE currency SET item_rounding = :rounding', ['rounding' => json_encode($rounding, \JSON_THROW_ON_ERROR)]);
        }

        $record = $this->connection->fetchAssociative(
            'SELECT item_rounding, total_rounding FROM currency WHERE id = :id',
            ['id' => $id]
        );

        static::assertIsArray($record);
        static::assertEquals($expected, json_decode((string) $record['item_rounding'], true, 512, \JSON_THROW_ON_ERROR));

        if ($decimals) {
            static::assertEquals($expected, json_decode((string) $record['total_rounding'], true, 512, \JSON_THROW_ON_ERROR));
        }
    }

    public function testOrderInsertTrigger(): void
    {
        $currencyId = Uuid::randomBytes();
        $data = [
            'id' => $currencyId,
            'item_rounding' => json_encode(['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01]),
            'total_rounding' => json_encode(['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01]),
            'factor' => 1,
            'symbol' => 'A',
            'iso_code' => 'de',
            'created_at' => (new \DateTime())->format('Y-m-d'),
        ];

        $this->connection->insert('currency', $data);

        $id = Uuid::randomBytes();
        $data = [
            'id' => $id,
            'version_id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => Uuid::randomBytes(),
            'currency_id' => $currencyId,
            'order_date_time' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => json_encode([]),
            'shipping_costs' => json_encode([]),
            'state_id' => $this->connection->fetchOne('SELECT id FROM state_machine_state LIMIT 1'),
            'currency_factor' => 1,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ];

        $this->connection->insert('`order`', $data);

        $order = $this->connection->fetchAssociative(
            'SELECT item_rounding, total_rounding FROM `order` WHERE id = :id',
            ['id' => $id]
        );

        static::assertIsArray($order);
        $expected = ['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01];

        $rounding = json_decode((string) $order['item_rounding'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($expected, $rounding);

        $rounding = json_decode((string) $order['total_rounding'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($expected, $rounding);
    }

    /**
     * @return array<string, array{0: int|null, 1: array{decimals: int, roundForNet: bool, interval: float}|null, 2: array{decimals: int, roundForNet: bool, interval: float}}>
     */
    public function currencyUpdateTriggerProvider(): array
    {
        return [
            'Update with old value' => [
                3,
                null,
                ['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01],
            ],
            'Write new value' => [
                null,
                ['decimals' => 3, 'roundForNet' => false, 'interval' => 0.02],
                ['decimals' => 3, 'roundForNet' => false, 'interval' => 0.02],
            ],
        ];
    }

    /**
     * @return array<string, array{0: int, 1: array{decimals: int, roundForNet: bool, interval: float}, 2?: array{decimals: int, roundForNet: bool, interval: float}}>
     */
    public function currencyInsertTriggerProvider(): array
    {
        return [
            'Writing old value 2' => [
                2,
                ['decimals' => 2, 'roundForNet' => true, 'interval' => 0.01],
            ],
            'Writing old value 3' => [
                3,
                ['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01],
            ],
            'Writing old and new value at same time' => [
                3,
                ['decimals' => 2, 'roundForNet' => false, 'interval' => 0.01],
                ['decimals' => 2, 'roundForNet' => false, 'interval' => 0.01],
            ],
        ];
    }

    /**
     * @return false|mixed[]
     */
    private function getTriggerInfo(string $triggerName): false|array
    {
        $database = $this->connection->fetchOne('SELECT DATABASE();');

        return $this->connection->fetchAssociative(
            '
                SELECT * FROM information_schema.`TRIGGERS`
                WHERE TRIGGER_SCHEMA = :database
                AND TRIGGER_NAME = :trigger',
            [
                'database' => $database,
                'trigger' => $triggerName,
            ]
        );
    }
}
