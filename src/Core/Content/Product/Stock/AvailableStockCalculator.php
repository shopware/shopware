<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class AvailableStockCalculator implements AvailableStockCalculatorInterface
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function calculate(string $productId, int $stock)
    {
        $this->connection->update(
            ProductDefinition::getEntityName(),
            [
                'available_stock' => $stock,
            ],
            [
                'id' => Uuid::fromHexToBytes($productId)
            ]
        );
    }
}