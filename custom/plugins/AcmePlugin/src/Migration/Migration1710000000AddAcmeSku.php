<?php declare(strict_types=1);

namespace Acme\AcmePlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710000000AddAcmeSku extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            INSERT IGNORE INTO `custom_field_set` (`id`, `name`, `config`, `active`, `created_at`)
            VALUES (
                UNHEX(REPLACE(\'a0000000000000000000000000000001\', \'-\', \'\')),
                \'acme_product_fields\',
                \'{"label":{"en-GB":"Acme Product Fields","de-DE":"Acme Produktfelder"}}\',
                1,
                NOW()
            )
        ');

        $connection->executeStatement('
            INSERT IGNORE INTO `custom_field_set_relation` (`id`, `set_id`, `entity_name`, `created_at`)
            VALUES (
                UNHEX(REPLACE(\'a0000000000000000000000000000002\', \'-\', \'\')),
                UNHEX(REPLACE(\'a0000000000000000000000000000001\', \'-\', \'\')),
                \'product\',
                NOW()
            )
        ');

        $connection->executeStatement('
            INSERT IGNORE INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `created_at`)
            VALUES (
                UNHEX(REPLACE(\'a0000000000000000000000000000003\', \'-\', \'\')),
                \'acme_sku\',
                \'text\',
                \'{"label":{"en-GB":"Acme SKU","de-DE":"Acme SKU"},"placeholder":{"en-GB":"e.g. ACME-12345"},"customFieldType":"text","customFieldPosition":1}\',
                1,
                UNHEX(REPLACE(\'a0000000000000000000000000000001\', \'-\', \'\')),
                NOW()
            )
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
