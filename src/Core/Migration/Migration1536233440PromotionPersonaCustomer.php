<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233440PromotionPersonaCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233440;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `promotion_persona_customer` (
                promotion_id BINARY(16) NOT NULL,
                customer_id BINARY(16) NOT NULL,
                PRIMARY KEY (`promotion_id`, `customer_id`),
                CONSTRAINT `fk.promotion_persona_customer.promotion_id` FOREIGN KEY (promotion_id)
                  REFERENCES promotion (id) ON DELETE CASCADE,
                CONSTRAINT `fk.promotion_persona_customer.customer_id` FOREIGN KEY (customer_id)
                  REFERENCES customer (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
       ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
