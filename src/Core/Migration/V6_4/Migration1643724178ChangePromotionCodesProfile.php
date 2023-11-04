<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1643724178ChangePromotionCodesProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1643724178;
    }

    public function update(Connection $connection): void
    {
        $id = $connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `name` = :name AND `system_default` = 1',
            ['name' => 'Default promotion codes']
        )->fetchOne();

        if ($id) {
            $mapping = $this->getMapping();
            $connection->update('import_export_profile', ['mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR)], ['id' => $id]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return list<array{key: string, mappedKey: string, position: int}>
     */
    private function getMapping(): array
    {
        return [
            ['key' => 'id', 'mappedKey' => 'id', 'position' => 0],
            ['key' => 'promotionId', 'mappedKey' => 'promotion_id', 'position' => 1],
            ['key' => 'code', 'mappedKey' => 'code', 'position' => 2],
        ];
    }
}
