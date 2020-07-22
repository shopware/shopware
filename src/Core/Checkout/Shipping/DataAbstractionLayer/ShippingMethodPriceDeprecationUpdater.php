<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.4.0 - deprecated since 6.3.0 will be removed in 6.4.0
 */
class ShippingMethodPriceDeprecationUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function updateByEvent(EntityWrittenEvent $event): void
    {
        $oldToNewIds = [];
        $newToOldIds = [];

        foreach ($event->getPayloads() as $payload) {
            if (isset($payload['currencyPrice']) && !isset($payload['price'])) {
                $newToOldIds[] = $payload['id'];
            }
            if (!isset($payload['currencyPrice']) && isset($payload['price'])) {
                $oldToNewIds[] = $payload['id'];
            }
        }
        if ($oldToNewIds !== []) {
            $this->setCurrencyPrices($oldToNewIds);
        }
        if ($newToOldIds !== []) {
            $this->setOldPrice($newToOldIds);
        }
    }

    public function updateByShippingMethodId(array $shippingMethodIds): void
    {
        $this->connection->executeUpdate(
            $this->getSetCurrencyPriceBaseQueryExpr() . '
            WHERE shipping_method_id IN (:shippingMethodIds)
            AND currency_price IS NULL
            AND price IS NOT NULL
            AND currency_id IS NOT NULL',
            ['shippingMethodIds' => Uuid::fromHexToBytesList($shippingMethodIds)],
            ['shippingMethodIds' => Connection::PARAM_STR_ARRAY]
        );

        // set deprecated price to first price in price object
        $this->connection->executeUpdate(
            $this->getSetOldPriceBaseQueryExpr() . '
            WHERE shipping_method_id IN (:shippingMethodIds)
            AND currency_price IS NOT NULL',
            ['shippingMethodIds' => Uuid::fromHexToBytesList($shippingMethodIds)],
            ['shippingMethodIds' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function setCurrencyPrices(array $shippingMethodPriceIds): void
    {
        $this->connection->executeUpdate(
            $this->getSetCurrencyPriceBaseQueryExpr() . '
            WHERE id IN (:shippingMethodPriceIds)
            AND price IS NOT NULL
            AND currency_id IS NOT NULL',
            ['shippingMethodPriceIds' => Uuid::fromHexToBytesList($shippingMethodPriceIds)],
            ['shippingMethodPriceIds' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function setOldPrice(array $shippingMethodPriceIds): void
    {
        $this->connection->executeUpdate(
            $this->getSetOldPriceBaseQueryExpr() . '
            WHERE id IN (:shippingMethodPriceIds)
            AND currency_price IS NOT NULL',
            ['shippingMethodPriceIds' => Uuid::fromHexToBytesList($shippingMethodPriceIds)],
            ['shippingMethodPriceIds' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function getSetCurrencyPriceBaseQueryExpr(): string
    {
        return '
            UPDATE shipping_method_price
            SET currency_price = JSON_OBJECT(
                CONCAT("c", LOWER(HEX(currency_id))),
                JSON_OBJECT(
                    "net", price,
                    "gross", price,
                    "linked", false,
                    "currencyId", LOWER(HEX(currency_id))
                )
            )
        ';
    }

    private function getSetOldPriceBaseQueryExpr(): string
    {
        return '
            UPDATE shipping_method_price
            SET
                price = JSON_UNQUOTE(JSON_EXTRACT(
                    currency_price,
                    CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(currency_price), "$[0]")), ".gross")
                )) + 0.0,
                currency_id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(
                    currency_price,
                    CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(currency_price), "$[0]")), ".currencyId")
                )))
        ';
    }
}
