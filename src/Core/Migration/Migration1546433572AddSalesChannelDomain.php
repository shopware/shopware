<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546433572AddSalesChannelDomain extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546433572;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
          CREATE TABLE sales_channel_domain (
            `id` binary(16) NOT NULL PRIMARY KEY,
            `sales_channel_id` binary(16) NOT NULL,
            `language_id` binary(16) NOT NULL,
            
            `url` varchar(255) NOT NULL,
            
            `currency_id` binary(16) NOT NULL,
            `snippet_set_id` binary(16) NOT NULL,

            `created_at` datetime(3) NOT NULL,
            `updated_at` datetime(3),
        
            CONSTRAINT `fk.sales_channel_domain.sales_channel_id`
              FOREIGN KEY (sales_channel_id) 
              REFERENCES `sales_channel` (id)
              ON DELETE CASCADE ON UPDATE CASCADE,
                  
            CONSTRAINT `fk.sales_channel_domain.language_id`
              FOREIGN KEY (sales_channel_id, language_id) 
              REFERENCES `sales_channel_language` (sales_channel_id, language_id)
              ON DELETE RESTRICT ON UPDATE CASCADE,
              
            CONSTRAINT `fk.sales_channel_domain.currency_id`
              FOREIGN KEY (currency_id) REFERENCES `currency` (id)
              ON DELETE RESTRICT ON UPDATE CASCADE,
              
            CONSTRAINT `fk.sales_channel_domain.snippet_set_id`
              FOREIGN KEY (snippet_set_id) REFERENCES `snippet_set` (id)
              ON DELETE RESTRICT ON UPDATE CASCADE,
              
            CONSTRAINT `uniq.sales_channel_domain.url` 
              UNIQUE(url)
        )');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
