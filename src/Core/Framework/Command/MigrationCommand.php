<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class MigrationCommand extends ContainerAwareCommand
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        parent::__construct();

        $this->container = $container;
    }

    protected function configure()
    {
        $this->addOption('destructive', 'd', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Get collection from directory: "' . __DIR__ . '/../../Version"');

        $collectionLoader = MigrationCollectionLoader::create();

        $collectionLoader->addDirectory(__DIR__ . '/../../Version', 'Shopware\Core\Version');

        $collection = $collectionLoader->getMigrationCollection();

        $output->writeln('Create Runner');

        $runner = MigrationRuntime::create('test_migration_table', $this->container);

        $output->writeln('migrate Migrations');

        $destructive = (bool) $input->getOption('destructive');

        $runner->migrate($collection, $destructive);
    }
}
