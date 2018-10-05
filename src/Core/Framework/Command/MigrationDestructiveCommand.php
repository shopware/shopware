<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

class MigrationDestructiveCommand extends MigrationCommand
{
    protected function getMigrationCommandName()
    {
        return 'database:migrate-destructive';
    }

    protected function getMigrationGenerator(?int $until, ?int $limit): \Generator
    {
        foreach ($this->runner->migrateDestructive($until, $limit) as $migration) {
            yield $migration;
        }
    }

    protected function getMigrationsCount(?int $until, ?int $limit)
    {
        return \count($this->runner->getExecutableDestructiveMigrations($until, $limit));
    }
}
