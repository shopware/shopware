<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673946817FixMediaFolderAssociationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673946817;
    }

    public function update(Connection $connection): void
    {
        $data = $connection->fetchAssociative('SELECT id, association_fields FROM media_default_folder WHERE entity = :user', ['user' => 'user']);

        if (!$data) {
            return;
        }

        $fields = \json_decode((string) $data['association_fields'], true);

        if (!\is_array($fields) || empty($fields)) {
            return;
        }

        $fields = \array_flip($fields);

        if (!isset($fields['avatarUser'])) {
            return;
        }

        unset($fields['avatarUser']);
        $fields['avatarUsers'] = true;

        $connection->executeStatement(
            '
            UPDATE media_default_folder
               SET association_fields = :association_fields 
            WHERE id = :id',
            ['id' => $data['id'], 'association_fields' => \json_encode(\array_keys($fields))]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
