<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1627929168UpdatePriceFieldInProductTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1627929168;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'UPDATE product
             SET price = JSON_SET(
                price,
                CONCAT("$.c", :currencyId, ".percentage"),
                JSON_OBJECT(
                    "net",
                    COALESCE(ROUND((
                        100 - JSON_UNQUOTE(JSON_EXTRACT(
                        price, CONCAT("$.c", :currencyId, ".net")
                        )) / JSON_UNQUOTE(JSON_EXTRACT(price, CONCAT("$.c", :currencyId, ".listPrice.net"))) * 100
                        ),2), 0),
                    "gross",
                    COALESCE(ROUND((
                        100 - JSON_UNQUOTE(JSON_EXTRACT(
                        price, CONCAT("$.c", :currencyId, ".gross")
                        )) / JSON_UNQUOTE(JSON_EXTRACT(price, CONCAT("$.c", :currencyId, ".listPrice.gross"))) * 100
                        ),2), 0)
                    )
             )
             WHERE JSON_UNQUOTE(JSON_EXTRACT(price, CONCAT("$.c", :currencyId, ".listPrice"))) IS NOT NULL',
            ['currencyId' => Defaults::CURRENCY]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
