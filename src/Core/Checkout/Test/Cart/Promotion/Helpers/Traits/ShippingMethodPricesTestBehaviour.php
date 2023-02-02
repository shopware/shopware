<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;

trait ShippingMethodPricesTestBehaviour
{
    /**
     * @var array
     */
    private $oldValues = [];

    /**
     * read all shipping method prices from db, store them in oldValues variable
     * and update all prices to $price value
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function setNewShippingPrices(Connection $conn, float $price): void
    {
        $rows = $conn->executeQuery(
            'SELECT id,currency_price FROM shipping_method_price'
        );

        foreach ($rows as $row) {
            if (\array_key_exists($row['id'], $this->oldValues)) {
                continue;
            }
            $this->oldValues[$row['id']] = $row['currency_price'];
        }

        $priceStruct = json_encode([
            'c' . Defaults::CURRENCY => [
                'currencyId' => Defaults::CURRENCY,
                'net' => $price,
                'gross' => $price,
                'linked' => false,
            ],
        ]);

        $conn->executeUpdate(
            'UPDATE shipping_method_price SET currency_price=:currencyPrice WHERE id in(:ids)',
            ['currencyPrice' => $priceStruct, 'ids' => array_keys($this->oldValues)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    /**
     * restore all prices that have been stored in $oldValues
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function restorePrices(Connection $conn): void
    {
        foreach ($this->oldValues as $k => $v) {
            $conn->executeUpdate(
                'UPDATE shipping_method_price SET currency_price=:currencyPrice WHERE id=:id',
                ['currencyPrice' => $v, 'id' => $k]
            );
        }
    }
}
