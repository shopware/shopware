<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Migration1717572627RemoveImportExportProfileName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1717572627;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'import_export_profile', 'technical_name')) {
            $this->addColumn(
                connection: $connection,
                table: 'import_export_profile',
                column: 'technical_name',
                type: 'VARCHAR(255)'
            );
        }

        if (!$this->indexExists($connection, ImportExportProfileDefinition::ENTITY_NAME, 'uniq.import_export_profile.technical_name')) {
            $connection->executeStatement('ALTER TABLE `import_export_profile` ADD CONSTRAINT `uniq.import_export_profile.technical_name` UNIQUE (`technical_name`)');
        }

        $names = $connection->executeQuery('SELECT id, name, technical_name FROM import_export_profile')->fetchAllAssociative();

        $technicalNames = [];
        foreach ($names as $name) {
            if ($name['technical_name'] !== null) {
                continue;
            }

            $technicalNames[] = [
                'id' => $name['id'],
                'technical_name' => $this->generateTechnicalName($name['name'], $technicalNames),
            ];
        }

        foreach ($technicalNames as $technicalName) {
            $connection->executeStatement(
                'UPDATE import_export_profile SET technical_name = :technical_name WHERE id = :id',
                [
                    'technical_name' => $technicalName['technical_name'],
                    'id' => $technicalName['id'],
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'import_export_profile', 'name');
    }

    /**
     * @param array<int, array<string, string>> $technicalNames
     */
    private function generateTechnicalName(?string $name, array $technicalNames): string
    {
        $name = $name ?? 'Unnamed profile';

        $technicalName = $this->getTechnicalName($name);

        // Check if the name already exists, if yes, add a number to the end
        $i = 1;
        $baseTechnicalName = $technicalName;
        while (\in_array($technicalName, array_column($technicalNames, 'technical_name'), true)) {
            $technicalName = $baseTechnicalName . '_' . $i++;
        }

        return $technicalName;
    }

    private function getTechnicalName(string $name): string
    {
        // Convert the name to lowercase and replace non-alphanumeric characters with underscores
        $technicalName = (string) preg_replace('/[^a-z0-9_]/', '_', strtolower($name));

        // Collapse consecutive underscores
        $technicalName = (string) preg_replace('/_+/', '_', $technicalName);

        // Remove leading and trailing underscores
        return trim($technicalName, '_');
    }
}
