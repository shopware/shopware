<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class Migration1594104496CashRoundingTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @dataProvider currencyInsertTriggerProvider
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
            $data['item_rounding'] = json_encode($initial);
            $data['total_rounding'] = json_encode($initial);
        }

        $this->connection->insert('currency', $data);

        $record = $this->connection->fetchAssoc(
            'SELECT item_rounding, total_rounding FROM currency WHERE id = :id',
            ['id' => $id]
        );

        static::assertEquals($expected, json_decode($record['item_rounding'], true));
        static::assertEquals($expected, json_decode($record['total_rounding'], true));
    }

    /**
     * @dataProvider currencyUpdateTriggerProvider
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
            $this->connection->executeUpdate('UPDATE currency SET decimal_precision = :decimals', ['decimals' => $decimals]);
        } else {
            $this->connection->executeUpdate('UPDATE currency SET item_rounding = :rounding', ['rounding' => json_encode($rounding)]);
        }

        $record = $this->connection->fetchAssoc(
            'SELECT item_rounding, total_rounding FROM currency WHERE id = :id',
            ['id' => $id]
        );
        static::assertEquals($expected, json_decode($record['item_rounding'], true));

        if ($decimals) {
            static::assertEquals($expected, json_decode($record['total_rounding'], true));
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
            'state_id' => $this->connection->fetchColumn('SELECT id FROM state_machine_state LIMIT 1'),
            'currency_factor' => 1,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ];

        $this->connection->insert('`order`', $data);

        $order = $this->connection->fetchAssoc(
            'SELECT item_rounding, total_rounding FROM `order` WHERE id = :id',
            ['id' => $id]
        );

        $expected = ['decimals' => 3, 'roundForNet' => true, 'interval' => 0.01];

        $rounding = json_decode($order['item_rounding'], true);
        static::assertEquals($expected, $rounding);

        $rounding = json_decode($order['total_rounding'], true);
        static::assertEquals($expected, $rounding);
    }

    public function currencyUpdateTriggerProvider()
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

    public function currencyInsertTriggerProvider()
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

    private function getTriggerInfo(string $triggerName)
    {
        $database = $this->connection->fetchColumn('SELECT DATABASE();');

        return $this->connection->fetchAssoc(
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
