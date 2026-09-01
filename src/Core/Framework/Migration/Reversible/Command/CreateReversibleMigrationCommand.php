<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible\Command;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[Package('framework')]
#[AsCommand(
    name: 'database:create-reversible-migration',
    description: 'Creates a new reversible migration file for a plugin',
)]
class CreateReversibleMigrationCommand extends Command
{
    use ResolvePluginTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly KernelPluginCollection $kernelPluginCollection,
        private readonly Filesystem $filesystem,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED, 'The name of the plugin the migration belongs to')
            ->addOption(
                'name',
                '',
                InputOption::VALUE_REQUIRED,
                'An optional descriptive name for the migration which will be used as a suffix for the filename.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pluginName = $input->getOption('plugin');
        if (!\is_string($pluginName) || $pluginName === '') {
            throw MigrationException::invalidArgument('Please specify the plugin the migration belongs to via --plugin.');
        }

        $name = (string) ($input->getOption('name') ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]*$/', $name)) {
            throw MigrationException::invalidArgument('Migration name contains forbidden characters!');
        }

        $plugin = $this->resolvePlugin($this->kernelPluginCollection, $pluginName);

        $directory = $plugin->getMigrationPath();
        $namespace = $plugin->getMigrationNamespace();
        $timestamp = $this->clock->now()->getTimestamp();

        $path = rtrim($directory, '/') . '/Migration' . $timestamp . $name . '.php';
        if ($this->filesystem->exists($path)) {
            throw MigrationException::invalidArgument(\sprintf('The migration file "%s" already exists.', $path));
        }

        $template = file_get_contents(__DIR__ . '/../Template/MigrationTemplateReversiblePlugin.txt');
        if ($template === false) {
            throw MigrationException::migrationFileDoesNotExist(__DIR__ . '/../Template/MigrationTemplateReversiblePlugin.txt');
        }

        $this->filesystem->dumpFile($path, str_replace(
            ['%%timestamp%%', '%%name%%', '%%namespace%%'],
            [(string) $timestamp, $name, $namespace],
            $template
        ));

        $io->success(\sprintf('Migration created: "%s"', $path));

        return self::SUCCESS;
    }
}
