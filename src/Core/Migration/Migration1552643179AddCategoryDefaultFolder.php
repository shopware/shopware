<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1552643179AddCategoryDefaultFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552643179;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('media_default_folder', [
            'id' => Uuid::uuid4()->getBytes(),
            'association_fields' => '["categories"]',
            'entity' => 'category',
            'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
