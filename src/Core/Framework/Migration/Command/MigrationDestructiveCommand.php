<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'database:migrate-destructive',
    description: 'Executes all migrations',
)]
#[Package('core')]
class MigrationDestructiveCommand extends MigrationCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'version-selection-mode',
            null,
            InputOption::VALUE_REQUIRED,
            'Define upto which version destructive migrations are executed. Possible values: "safe", "blue-green", "all".',
            MigrationCollectionLoader::VERSION_SELECTION_SAFE
        );
    }

    protected function getMigrationGenerator(MigrationCollection $collection, ?int $until, ?int $limit): \Generator
    {
        yield from $collection->migrateDestructiveInSteps($until, $limit);
    }

    protected function getMigrationsCount(MigrationCollection $collection, ?int $until, ?int $limit): int
    {
        return \count($collection->getExecutableDestructiveMigrations($until, $limit));
    }

    protected function collectMigrations(InputInterface $input, string $identifier): MigrationCollection
    {
        if ($identifier === 'core') {
            $mode = $input->getOption('version-selection-mode');
            if (!\is_string($mode)) {
                throw new \InvalidArgumentException('version-selection-mode should be a string');
            }

            return $this->loader->collectAllForVersion($this->shopwareVersion, $mode);
        }

        return $this->loader->collect($identifier);
    }
}
