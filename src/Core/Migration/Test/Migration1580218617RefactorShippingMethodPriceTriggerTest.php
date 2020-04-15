<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1580218617RefactorShippingMethodPriceTriggerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testInsertTriggerWithOldSchema(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $shippingMethodId = $connection->executeQuery('SELECT id FROM shipping_method LIMIT 1')->fetch(FetchMode::COLUMN);
        $currencyId = $connection->executeQuery('SELECT id FROM currency LIMIT 1')->fetch(FetchMode::COLUMN);

        $shippingPriceId = Uuid::randomBytes();

        $connection->executeUpdate('
                INSERT INTO `shipping_method_price` (`id`, `shipping_method_id`, `price`, `currency_id`, `created_at`)
                VALUES (:id, :shippingMethodId, 10.999, :currencyId, NOW())
            ', [
            'id' => $shippingPriceId,
            'shippingMethodId' => $shippingMethodId,
            'currencyId' => $currencyId,
        ]);

        $insertedShippingMethodPrice = $connection->executeQuery(
            'SELECT * FROM shipping_method_price WHERE id = :id',
            ['id' => $shippingPriceId]
        )->fetch(FetchMode::ASSOCIATIVE);

        $expectedCurrencyPrice = [
            'c' . Uuid::fromBytesToHex($currencyId) => [
                'net' => 10.999,
                'gross' => 10.999,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($currencyId),
            ],
        ];

        $actualCurrencyPrice = json_decode($insertedShippingMethodPrice['currency_price'], true);

        static::assertSame($expectedCurrencyPrice, $actualCurrencyPrice);
        static::assertSame($currencyId, $insertedShippingMethodPrice['currency_id']);
        static::assertSame(10.999, (float) $insertedShippingMethodPrice['price']);
    }

    public function testInsertTriggerWithNewSchema(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $shippingMethodId = $connection->executeQuery('SELECT id FROM shipping_method LIMIT 1')->fetch(FetchMode::COLUMN);
        $currencyId = $connection->executeQuery('SELECT id FROM currency LIMIT 1')->fetch(FetchMode::COLUMN);

        $shippingPriceId = Uuid::randomBytes();

        $currencyPrice = [
            'c' . Uuid::fromBytesToHex($currencyId) => [
                'net' => 10,
                'gross' => 10,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($currencyId),
            ],
        ];

        $connection->executeUpdate('
                INSERT INTO `shipping_method_price` (`id`, `shipping_method_id`, `currency_price`, `created_at`)
                VALUES (:id, :shippingMethodId, :currencyPrice, NOW())
            ', [
            'id' => $shippingPriceId,
            'shippingMethodId' => $shippingMethodId,
            'currencyPrice' => json_encode($currencyPrice),
        ]);

        $insertedShippingMethodPrice = $connection->executeQuery(
            'SELECT * FROM shipping_method_price WHERE id = :id',
            ['id' => $shippingPriceId]
        )->fetch(FetchMode::ASSOCIATIVE);

        $actualCurrencyPrice = json_decode($insertedShippingMethodPrice['currency_price'], true);

        static::assertSame($currencyPrice, $actualCurrencyPrice);
        static::assertSame($currencyId, $insertedShippingMethodPrice['currency_id']);
        static::assertSame(10, (int) $insertedShippingMethodPrice['price']);
    }

    public function testUpdateTriggerWithNewSchemaWithNullValuesBefore(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $shippingMethodId = $connection->executeQuery('SELECT id FROM shipping_method LIMIT 1')->fetch(FetchMode::COLUMN);
        $currencyId = $connection->executeQuery('SELECT id FROM currency LIMIT 1')->fetch(FetchMode::COLUMN);

        $shippingPriceId = Uuid::randomBytes();

        $connection->executeUpdate('
                INSERT INTO `shipping_method_price` (`id`, `shipping_method_id`, `created_at`)
                VALUES (:id, :shippingMethodId, NOW())
            ', [
            'id' => $shippingPriceId,
            'shippingMethodId' => $shippingMethodId,
        ]);

        $currencyPrice = [
            'c' . Uuid::fromBytesToHex($currencyId) => [
                'net' => 10,
                'gross' => 10,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($currencyId),
            ],
        ];

        $connection->executeUpdate('
                UPDATE `shipping_method_price`
                SET `currency_price` = :currencyPrice WHERE id = :id
            ', [
            'id' => $shippingPriceId,
            'currencyPrice' => json_encode($currencyPrice),
        ]);

        $insertedShippingMethodPrice = $connection->executeQuery(
            'SELECT * FROM shipping_method_price WHERE id = :id',
            ['id' => $shippingPriceId]
        )->fetch(FetchMode::ASSOCIATIVE);

        $actualCurrencyPrice = json_decode($insertedShippingMethodPrice['currency_price'], true);

        static::assertSame($currencyPrice, $actualCurrencyPrice);
        static::assertSame($currencyId, $insertedShippingMethodPrice['currency_id']);
        static::assertSame(10, (int) $insertedShippingMethodPrice['price']);
    }

    public function testUpdateTriggerWithNewSchema(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $shippingMethodId = $connection->executeQuery('SELECT id FROM shipping_method LIMIT 1')->fetch(FetchMode::COLUMN);
        $currencyIdBefore = $connection->executeQuery('SELECT id FROM currency LIMIT 1')->fetch(FetchMode::COLUMN);

        $shippingPriceId = Uuid::randomBytes();

        $currencyPriceBefore = [
            'c' . Uuid::fromBytesToHex($currencyIdBefore) => [
                'net' => 5,
                'gross' => 5,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($currencyIdBefore),
            ],
        ];

        $connection->executeUpdate('
                INSERT INTO `shipping_method_price` (`id`, `shipping_method_id`, `price`, `currency_id`, `currency_price`, `created_at`)
                VALUES (:id, :shippingMethodId, :price, :currencyId, :currencyPrice, NOW())
            ', [
            'id' => $shippingPriceId,
            'shippingMethodId' => $shippingMethodId,
            'price' => 5,
            'currencyId' => $currencyIdBefore,
            'currencyPrice' => json_encode($currencyPriceBefore),
        ]);

        $updatedCurrencyId = $connection->executeQuery('SELECT id FROM currency LIMIT 1 OFFSET 1')->fetch(FetchMode::COLUMN);
        static::assertNotSame($updatedCurrencyId, $currencyIdBefore);

        $updatedCurrencyPrice = [
            'c' . Uuid::fromBytesToHex($updatedCurrencyId) => [
                'net' => 10,
                'gross' => 10,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($updatedCurrencyId),
            ],
        ];

        $connection->executeUpdate('
                UPDATE `shipping_method_price`
                SET `currency_price` = :currencyPrice WHERE id = :id
            ', [
            'id' => $shippingPriceId,
            'currencyPrice' => json_encode($updatedCurrencyPrice),
        ]);

        $insertedShippingMethodPrice = $connection->executeQuery(
            'SELECT * FROM shipping_method_price WHERE id = :id',
            ['id' => $shippingPriceId]
        )->fetch(FetchMode::ASSOCIATIVE);

        $actualCurrencyPrice = json_decode($insertedShippingMethodPrice['currency_price'], true);

        static::assertSame($updatedCurrencyPrice, $actualCurrencyPrice);
        static::assertSame($updatedCurrencyId, $insertedShippingMethodPrice['currency_id']);
        static::assertSame(10, (int) $insertedShippingMethodPrice['price']);
    }

    public function testUpdateTriggerWithOldSchemaWithNullValuesBefore(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $shippingMethodId = $connection->executeQuery('SELECT id FROM shipping_method LIMIT 1')->fetch(FetchMode::COLUMN);
        $currencyIdBefore = $connection->executeQuery('SELECT id FROM currency LIMIT 1')->fetch(FetchMode::COLUMN);

        $shippingPriceId = Uuid::randomBytes();

        $currencyPriceBefore = [
            'c' . Uuid::fromBytesToHex($currencyIdBefore) => [
                'net' => 5.999,
                'gross' => 5.999,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($currencyIdBefore),
            ],
        ];

        $connection->executeUpdate('
                INSERT INTO `shipping_method_price` (`id`, `shipping_method_id`, `price`, `currency_id`, `currency_price`, `created_at`)
                VALUES (:id, :shippingMethodId, :price, :currencyId, :currencyPrice, NOW())
            ', [
            'id' => $shippingPriceId,
            'shippingMethodId' => $shippingMethodId,
            'price' => 5.999,
            'currencyId' => $currencyIdBefore,
            'currencyPrice' => json_encode($currencyPriceBefore),
        ]);

        $updatedCurrencyId = $connection->executeQuery('SELECT id FROM currency LIMIT 1 OFFSET 1')->fetch(FetchMode::COLUMN);
        static::assertNotSame($updatedCurrencyId, $currencyIdBefore);

        $connection->executeUpdate('
                UPDATE `shipping_method_price`
                SET `price` = 10.999, `currency_id` = :currencyId WHERE id = :id
            ', [
            'id' => $shippingPriceId,
            'currencyId' => $updatedCurrencyId,
        ]);

        $insertedShippingMethodPrice = $connection->executeQuery(
            'SELECT * FROM shipping_method_price WHERE id = :id',
            ['id' => $shippingPriceId]
        )->fetch(FetchMode::ASSOCIATIVE);

        $expectedCurrencyPrice = [
            'c' . Uuid::fromBytesToHex($updatedCurrencyId) => [
                'net' => 10.999,
                'gross' => 10.999,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($updatedCurrencyId),
            ],
        ];

        $actualCurrencyPrice = json_decode($insertedShippingMethodPrice['currency_price'], true);

        static::assertSame($expectedCurrencyPrice, $actualCurrencyPrice);
        static::assertSame($updatedCurrencyId, $insertedShippingMethodPrice['currency_id']);
        static::assertSame(10, (int) $insertedShippingMethodPrice['price']);
    }

    public function testUpdateTriggerWithOldSchema(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $shippingMethodId = $connection->executeQuery('SELECT id FROM shipping_method LIMIT 1')->fetch(FetchMode::COLUMN);
        $currencyId = $connection->executeQuery('SELECT id FROM currency LIMIT 1')->fetch(FetchMode::COLUMN);

        $shippingPriceId = Uuid::randomBytes();

        $connection->executeUpdate('
                INSERT INTO `shipping_method_price` (`id`, `shipping_method_id`, `created_at`)
                VALUES (:id, :shippingMethodId, NOW())
            ', [
            'id' => $shippingPriceId,
            'shippingMethodId' => $shippingMethodId,
        ]);

        $connection->executeUpdate('
                UPDATE `shipping_method_price`
                SET `price` = 10.3, `currency_id` = :currencyId WHERE id = :id
            ', [
            'id' => $shippingPriceId,
            'currencyId' => $currencyId,
        ]);

        $insertedShippingMethodPrice = $connection->executeQuery(
            'SELECT * FROM shipping_method_price WHERE id = :id',
            ['id' => $shippingPriceId]
        )->fetch(FetchMode::ASSOCIATIVE);

        $expectedCurrencyPrice = [
            'c' . Uuid::fromBytesToHex($currencyId) => [
                'net' => 10.3,
                'gross' => 10.3,
                'linked' => false,
                'currencyId' => Uuid::fromBytesToHex($currencyId),
            ],
        ];

        $actualCurrencyPrice = json_decode($insertedShippingMethodPrice['currency_price'], true);

        static::assertSame($expectedCurrencyPrice, $actualCurrencyPrice);
        static::assertSame($currencyId, $insertedShippingMethodPrice['currency_id']);
        static::assertSame(10, (int) $insertedShippingMethodPrice['price']);
    }
}
