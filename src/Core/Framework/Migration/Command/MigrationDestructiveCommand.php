<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Migration\MigrationCollection;

class MigrationDestructiveCommand extends MigrationCommand
{
    protected static $defaultName = 'database:migrate-destructive';

    protected function getMigrationGenerator(MigrationCollection $collection, ?int $until, ?int $limit): \Generator
    {
        yield from $collection->migrateDestructiveInSteps($until, $limit);
    }

    protected function getMigrationsCount(MigrationCollection $collection, ?int $until, ?int $limit): int
    {
        return \count($collection->getExecutableDestructiveMigrations($until, $limit));
    }
}
