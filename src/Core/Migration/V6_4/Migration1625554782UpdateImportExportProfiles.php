<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1625554782UpdateImportExportProfiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625554782;
    }

    public function update(Connection $connection): void
    {
        $this->updateProfileForEntity($connection, 'product', $this->getRequiredProductMappings());
        $this->updateProfileForEntity($connection, 'property_group_option', $this->getRequiredPropertyMappings());
        $this->updateProfileForEntity($connection, 'newsletter_recipient', $this->getRequiredNewsletterRecipientMappings());
        $this->updateProfileForEntity($connection, 'category', $this->getRequiredCategoryMappings());
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateProfileForEntity(Connection $connection, string $entityName, array $requiredMappings): void
    {
        // fetch profile from database and get the first one
        $profile = $connection->fetchAllAssociative(
            'SELECT `id`, `mapping` FROM `import_export_profile` WHERE `system_default` = 1 AND `source_entity` = :entityName',
            ['entityName' => $entityName]
        )[0];
        $dbMappings = \json_decode($profile['mapping'], true);

        // add fields requiredByUser, useDefaultValue, useDefaultValue and requiredBySystem to mapping
        foreach ($dbMappings as $key => $mapping) {
            $mappingKey = $mapping['key'];

            $dbMappings[$key]['requiredByUser'] = false;
            $dbMappings[$key]['useDefaultValue'] = false;
            $dbMappings[$key]['defaultValue'] = null;
            $dbMappings[$key]['requiredBySystem'] = \in_array($mappingKey, $requiredMappings, true);
        }

        // update profile in the database
        $connection->executeStatement(
            'UPDATE `import_export_profile` SET `mapping` = :mapping WHERE `source_entity` = :entityName',
            [
                'mapping' => \json_encode($dbMappings),
                'entityName' => $entityName,
            ]
        );
    }

    private function getRequiredProductMappings(): array
    {
        return [
            'id',
            'tax.id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'productNumber',
            'stock',
            'translations.DEFAULT.name',
        ];
    }

    private function getRequiredPropertyMappings(): array
    {
        return [
            'id',
            'group.id',
            'translations.DEFAULT.name',
        ];
    }

    private function getRequiredNewsletterRecipientMappings(): array
    {
        return [
            'id',
            'salesChannel.id',
            'translations.DEFAULT.name',
        ];
    }

    private function getRequiredCategoryMappings(): array
    {
        return [
            'id',
            'translations.DEFAULT.name',
        ];
    }
}
