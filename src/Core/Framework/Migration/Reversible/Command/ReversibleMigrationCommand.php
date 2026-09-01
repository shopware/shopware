<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\MigrationRunner;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('framework')]
#[AsCommand(
    name: 'database:migrate-reversible',
    description: 'Executes pending reversible migrations of a plugin',
)]
class ReversibleMigrationCommand extends Command
{
    use ResolvePluginTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly MigrationRunner $migrationRunner,
        private readonly KernelPluginCollection $kernelPluginCollection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('plugin', InputArgument::OPTIONAL, 'The name of the plugin to migrate')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Migrate every active plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->collectPlugins($input) as $plugin) {
            $applied = $this->migrationRunner->up($plugin);

            if ($applied === []) {
                $io->writeln(\sprintf('%s is already up to date.', $plugin->getName()));

                continue;
            }

            $io->section($plugin->getName());
            $io->listing($applied);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Plugin>
     */
    private function collectPlugins(InputInterface $input): array
    {
        $pluginName = $input->getArgument('plugin');

        if ($input->getOption('all')) {
            if ($pluginName !== null) {
                throw MigrationException::invalidArgument('Pass either a plugin name or the --all option, not both.');
            }

            // getActives() is keyed by class name, so sort by plugin name for a deterministic order
            $plugins = array_values($this->kernelPluginCollection->getActives());
            usort($plugins, static fn (Plugin $a, Plugin $b): int => $a->getName() <=> $b->getName());

            return $plugins;
        }

        if ($pluginName === null) {
            throw MigrationException::invalidArgument('Missing plugin name or --all option.');
        }

        return [$this->resolvePlugin($this->kernelPluginCollection, (string) $pluginName)];
    }
}
