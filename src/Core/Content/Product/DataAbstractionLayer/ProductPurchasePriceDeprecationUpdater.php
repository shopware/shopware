<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.4.0 - class will be removed in 6.4.0
 */
class ProductPurchasePriceDeprecationUpdater
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
            if (isset($payload['purchasePrices']) && !isset($payload['purchasePrice'])) {
                $newToOldIds[] = $payload['id'];
            }
            if (!isset($payload['purchasePrices']) && isset($payload['purchasePrice'])) {
                $oldToNewIds[] = $payload['id'];
            }
        }
        if ($oldToNewIds !== []) {
            $this->setPurchasePrices($oldToNewIds);
        }
        if ($newToOldIds !== []) {
            $this->setOldPrice($newToOldIds);
        }
    }

    public function updateByProductId(array $productIds): void
    {
        $this->connection->executeUpdate(
            $this->getSetPurchasePricesBaseQueryExpr() . '
            WHERE id IN (:productIds)
            AND purchase_prices IS NULL
            AND parent_id IS NULL
            AND purchase_price IS NOT NULL',
            [
                'productIds' => Uuid::fromHexToBytesList($productIds),
                'currency_id' => Defaults::CURRENCY,
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ]
        );

        // set deprecated price to first price in price object
        $this->connection->executeUpdate(
            $this->getSetOldPurchasePriceBaseQueryExpr() . '
            WHERE id IN (:productIds)
            AND purchase_price IS NOT NULL',
            ['productIds' => Uuid::fromHexToBytesList($productIds)],
            ['productIds' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function setPurchasePrices(array $productIds): void
    {
        $this->connection->executeUpdate(
            $this->getSetPurchasePricesBaseQueryExpr() . '
            WHERE id IN (:productIds)
            AND purchase_price IS NOT NULL',
            ['productIds' => Uuid::fromHexToBytesList($productIds)],
            ['productIds' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function setOldPrice(array $productIds): void
    {
        $this->connection->executeUpdate(
            $this->getSetOldPurchasePriceBaseQueryExpr() . '
            WHERE id IN (:productIds)
            AND purchase_prices IS NOT NULL',
            ['productIds' => Uuid::fromHexToBytesList($productIds)],
            ['productIds' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function getSetPurchasePricesBaseQueryExpr(): string
    {
        return '
            UPDATE product
            SET purchase_prices = JSON_OBJECT(
                "c' . Defaults::CURRENCY . '",
                JSON_OBJECT(
                    "net", purchase_price,
                    "gross", purchase_price,
                    "linked", false,
                    "currencyId", LOWER("' . Defaults::CURRENCY . '")
                )
            )
        ';
    }

    private function getSetOldPurchasePriceBaseQueryExpr(): string
    {
        return '
            UPDATE product
            SET
                purchase_price = JSON_UNQUOTE(JSON_EXTRACT(
                    purchase_prices,
                    CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(purchase_prices), "$[0]")), ".gross")
                )) + 0.0
        ';
    }
}
