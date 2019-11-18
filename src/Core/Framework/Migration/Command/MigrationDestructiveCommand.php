<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

class MigrationDestructiveCommand extends MigrationCommand
{
    protected static $defaultName = 'database:migrate-destructive';

    protected function getMigrationGenerator(?int $until, ?int $limit): \Generator
    {
        yield from $this->runner->migrateDestructive($until, $limit);
    }

    protected function getMigrationsCount(?int $until, ?int $limit): int
    {
        return \count($this->runner->getExecutableDestructiveMigrations($until, $limit));
    }
}
