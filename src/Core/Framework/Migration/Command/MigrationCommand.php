<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends Command
{
    protected static $defaultName = 'database:migrate';

    /**
     * @var MigrationCollectionLoader
     */
    protected $loader;

    /**
     * @var MigrationRuntime
     */
    protected $runner;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    public function __construct(
        MigrationCollectionLoader $loader,
        MigrationRuntime $runner,
        TagAwareAdapterInterface $cache
    ) {
        parent::__construct();

        $this->loader = $loader;
        $this->runner = $runner;
        $this->cache = $cache;
    }

    protected function getMigrationGenerator(MigrationCollection $collection, ?int $until, ?int $limit): \Generator
    {
        yield from $collection->migrateInSteps($until, $limit);
    }

    protected function getMigrationsCount(MigrationCollection $collection, ?int $until, ?int $limit): int
    {
        return \count($collection->getExecutableMigrations($until, $limit));
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identifier', InputArgument::OPTIONAL, 'identifier to determine which migrations to run', 'core')
            ->addArgument('until', InputArgument::OPTIONAL, 'timestamp cap for migrations')
            ->addOption('all', 'all', InputOption::VALUE_NONE, 'no migration timestamp cap')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getArgument('until') && !$input->getOption('all')) {
            throw new \InvalidArgumentException('missing timestamp cap or --all option');
        }

        $this->io = new ShopwareStyle($input, $output);

        $this->io->writeln('Get collection from directories');

        $collection = $this->loader->collect($input->getArgument('identifier'));
        $collection->sync();

        $this->io->writeln('migrate Migrations');

        $until = (int) $input->getArgument('until');
        $limit = (int) $input->getOption('limit');

        if ($input->getOption('all')) {
            $until = null;
        }

        $total = $this->getMigrationsCount($collection, $until, $limit);
        $this->io->progressStart($total);
        $migratedCounter = 0;

        try {
            foreach ($this->getMigrationGenerator($collection, $until, $limit) as $_return) {
                $this->io->progressAdvance();
                ++$migratedCounter;
            }
        } catch (\Exception $e) {
            $this->finishProgress($migratedCounter, $total);

            throw new MigrateException($e->getMessage() . PHP_EOL . 'Trace: ' . PHP_EOL . $e->getTraceAsString(), $e);
        }

        $this->finishProgress($migratedCounter, $total);
        $this->io->writeln('all migrations executed');

        $this->cache->clear();
        $this->io->writeln('cleared the shopware cache');

        return 0;
    }

    private function finishProgress(int $migrated, int $total): void
    {
        if ($migrated === $total) {
            $this->io->progressFinish();
        }

        $this->io->table(
            ['Action', 'Number of migrations'],
            [
                ['Migrated', $migrated . ' out of ' . $total],
            ]
        );
    }
}
