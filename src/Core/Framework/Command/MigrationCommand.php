<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationCommand extends ContainerAwareCommand
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
        $this->addOption('destructive', 'd', InputOption::VALUE_NONE)
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Get collection from directories');

        foreach ($this->directories as $namespace => $directory) {
            $this->collector->addDirectory($directory, $namespace);
        }

        $this->collector->syncMigrationCollection();

        $output->writeln('migrate Migrations');

        $destructive = (bool) $input->getOption('destructive');
        $limit = (int) $input->getOption('limit');

        try {
            $this->runner->migrate($destructive, $limit);
        } catch (\Exception $e) {
            $output->writeln('migrate error "' . $e->getMessage() . '"');

            return;
        }

        $output->writeln('all migrations executed');
    }
}
