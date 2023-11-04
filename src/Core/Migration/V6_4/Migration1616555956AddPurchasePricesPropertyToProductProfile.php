<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1616555956AddPurchasePricesPropertyToProductProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1616555956;
    }

    public function update(Connection $connection): void
    {
        $id = $connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `name` = :name AND `system_default` = 1',
            ['name' => 'Default product']
        )->fetchOne();
        if ($id) {
            $productMappingProfile = require __DIR__ . '/../Fixtures/import-export-profiles/ProductMappingProfile.php';
            $connection->update('import_export_profile', ['mapping' => json_encode($productMappingProfile, \JSON_THROW_ON_ERROR)], ['id' => $id]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
