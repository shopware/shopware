<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Migration1717573310ImportExportTechnicalNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1717573310;
    }

    public function update(Connection $connection): void
    {
        $names = $connection->
            executeQuery('SELECT id, name FROM import_export_profile WHERE technical_name IS NULL')
            ->fetchAllAssociative();

        $existingTechnicalNames = $connection
            ->executeQuery('SELECT technical_name FROM import_export_profile WHERE technical_name IS NOT NULL')
            ->fetchFirstColumn();

        $technicalNames = [];
        foreach ($names as $name) {
            $newTechnicalName = $this->generateTechnicalName($name['name'], $existingTechnicalNames);
            $existingTechnicalNames[] = $newTechnicalName;
            $technicalNames[] = [
                'id' => $name['id'],
                'technical_name' => $newTechnicalName,
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

        $manager = $connection->createSchemaManager();
        $columns = $manager->listTableColumns(ImportExportProfileDefinition::ENTITY_NAME);

        if (\array_key_exists('technical_name', $columns) && !$columns['technical_name']->getNotnull()) {
            $connection
                ->executeStatement('ALTER TABLE `import_export_profile` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }
    }

    /**
     * @param array<int, string> $existingTechnicalNames
     */
    private function generateTechnicalName(?string $name, array $existingTechnicalNames): string
    {
        $name = $name ?? 'Unnamed profile';

        if (empty(trim($name))) {
            $name = 'Unnamed profile';
        }

        $technicalName = $this->getTechnicalName($name);

        // Check if the name already exists, if yes, add a number to the end
        $i = 1;
        $baseTechnicalName = $technicalName;
        while (\in_array($technicalName, $existingTechnicalNames, true)) {
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
