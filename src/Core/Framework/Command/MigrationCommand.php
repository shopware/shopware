<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends Command
{
    /**
     * @var string[]
     */
    private $directories;

    /**
     * @var MigrationCollectionLoader
     */
    private $collector;

    /**
     * @var MigrationRuntime
     */
    private $runner;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @param string[] $directories
     */
    public function __construct(
        MigrationCollectionLoader $collector,
        MigrationRuntime $runner,
        array $directories
    ) {
        parent::__construct();

        $this->collector = $collector;
        $this->runner = $runner;
        $this->directories = $directories;
    }

    protected function configure()
    {
        $this->setName('database:migrate')
            ->addArgument('until', InputArgument::OPTIONAL, 'timestamp cap for migrations')
            ->addOption('all', 'all', InputOption::VALUE_NONE, 'no migration timestamp cap')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getArgument('until') && !$input->getOption('all')) {
            throw new \InvalidArgumentException('missing timestamp cap or --all option');
        }

        $this->io = new SymfonyStyle($input, $output);

        $this->io->writeln('Get collection from directories');

        foreach ($this->directories as $namespace => $directory) {
            $this->collector->addDirectory($directory, $namespace);
        }

        $this->collector->syncMigrationCollection();

        $this->io->writeln('migrate Migrations');

        $until = (int) $input->getArgument('until');
        $limit = (int) $input->getOption('limit');

        if ($input->getOption('all')) {
            $until = null;
        }

        $total = count($this->runner->getExecutableMigrations($until, $limit));
        $this->io->progressStart($total);
        $migratedCounter = 0;

        try {
            $generator = $this->runner->migrate($until, $limit);
            foreach ($generator as $key => $return) {
                $this->io->progressAdvance();
                ++$migratedCounter;
            }
        } catch (\Exception $e) {
            $this->finishProgress($migratedCounter, $total);
            throw new MigrateException('Migration Error: "' . $e->getMessage() . '"');
        }

        $this->finishProgress($migratedCounter, $total);
        $this->io->writeln('all migrations executed');
    }

    private function finishProgress(int $migrated, int $total)
    {
        if ($migrated === $total) {
            $this->io->progressFinish();
        }

        $this->io->table(
            ['Action', 'Number of migrations'],
            [
                ['Migrated', $migrated . ' from ' . $total],
            ]
        );
    }
}
