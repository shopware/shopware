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
        yield from $this->runner->migrateDestructive($until, $limit);
    }

    protected function getMigrationsCount(?int $until, ?int $limit)
    {
        return \count($this->runner->getExecutableDestructiveMigrations($until, $limit));
    }
}
