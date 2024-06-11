<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationException;
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
            sprintf(
                'Define upto which version destructive migrations are executed. Possible values: "%s".',
                implode('", "', MigrationCollectionLoader::VALID_VERSION_SELECTION_SAFE_VALUES)
            ),
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
            $mode = (string) $input->getOption('version-selection-mode');
            if (!\in_array($mode, MigrationCollectionLoader::VALID_VERSION_SELECTION_SAFE_VALUES, true)) {
                throw MigrationException::invalidVersionSelectionMode($mode);
            }

            return $this->loader->collectAllForVersion($this->shopwareVersion, $mode);
        }

        return $this->loader->collect($identifier);
    }
}
