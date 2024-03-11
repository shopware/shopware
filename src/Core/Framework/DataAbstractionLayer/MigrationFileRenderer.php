<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class MigrationFileRenderer
{
    /**
     * @param string[] $queries
     */
    public function render(
        string $namespace,
        string $className,
        string $timestamp,
        array $queries,
        string $package = 'core'
    ): string {
        $formattedSql = $this->formatSqlQueries($queries);

        $stubPath = __DIR__ . '/stubs/migration.stub';
        $stub = file_get_contents($stubPath);

        if ($stub === false) {
            throw DataAbstractionLayerException::migrationStubNotFound($stubPath);
        }

        return str_replace(
            ['{Namespace}', '{ClassName}', '{Timestamp}', '{SqlQueries}', '{Package}'],
            [$namespace, $className, $timestamp, $formattedSql, $package],
            $stub
        );
    }

    public static function createMigrationClassName(string $timestamp, string $entity): string
    {
        return 'Migration' . $timestamp . ucfirst(str_replace('_', '', ucwords($entity, '_')));
    }

    /**
     * @param string[] $queries
     */
    private function formatSqlQueries(array $queries): string
    {
        return implode(";\n\n", $queries) . ';';
    }
}
