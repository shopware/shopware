<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Generator;

class MigrationDestructiveCommand extends MigrationCommand
{
    protected function getMigrationCommandName(): string
    {
        return 'database:migrate-destructive';
    }

    protected function getMigrationGenerator(?int $until, ?int $limit): Generator
    {
        yield from $this->runner->migrateDestructive($until, $limit);
    }

    protected function getMigrationsCount(?int $until, ?int $limit): int
    {
        return \count($this->runner->getExecutableDestructiveMigrations($until, $limit));
    }
}
