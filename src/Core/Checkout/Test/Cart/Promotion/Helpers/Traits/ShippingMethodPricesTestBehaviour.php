<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Doctrine\DBAL\Connection;

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
            'SELECT id,price FROM shipping_method_price'
        );

        foreach ($rows as $row) {
            if (array_key_exists($row['id'], $this->oldValues)) {
                continue;
            }
            $this->oldValues[$row['id']] = $row['price'];
        }

        $conn->executeUpdate(
            'UPDATE shipping_method_price SET price=:price WHERE id in(:ids)',
            ['price' => $price, 'ids' => array_keys($this->oldValues)],
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
                'UPDATE shipping_method_price SET price=:price WHERE id=:id',
                ['price' => $v, 'id' => $k]
            );
        }
    }
}
