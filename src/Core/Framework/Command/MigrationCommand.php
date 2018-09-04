<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Migration\Event\MigrateAdvanceEvent;
use Shopware\Core\Framework\Migration\Event\MigrateFinishEvent;
use Shopware\Core\Framework\Migration\Event\MigrateStartEvent;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MigrationCommand extends ContainerAwareCommand implements EventSubscriberInterface
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

    public static function getSubscribedEvents()
    {
        return [
            MigrateAdvanceEvent::EVENT_NAME => 'onAdvance',
            MigrateStartEvent::EVENT_NAME => 'onStart',
            MigrateFinishEvent::EVENT_NAME => 'onFinish',
        ];
    }

    public function onStart(MigrateStartEvent $event)
    {
        if (!$this->io) {
            return;
        }

        $this->io->progressStart($event->getNumberOfMigrations());
    }

    public function onAdvance(MigrateAdvanceEvent $event)
    {
        if (!$this->io) {
            return;
        }

        $this->io->progressAdvance();
    }

    public function onFinish(MigrateFinishEvent $event)
    {
        if (!$this->io) {
            return;
        }

        if ($event->getMigrated() === $event->getTotal()) {
            $this->io->progressFinish();
        }

        $this->io->table(
            ['Action', 'Number of migrations'],
            [
                ['Migrated', $event->getMigrated() . ' from ' . $event->getTotal()],
            ]
        );
    }

    protected function configure()
    {
        $this->addOption('destructive', 'd', InputOption::VALUE_NONE)
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '', 0)
            ->addArgument('timeStamp', InputArgument::REQUIRED, 'timestamp cap for migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->writeln('Get collection from directories');

        foreach ($this->directories as $namespace => $directory) {
            $this->collector->addDirectory($directory, $namespace);
        }

        $this->collector->syncMigrationCollection();

        $this->io->writeln('migrate Migrations');

        $destructive = (bool) $input->getOption('destructive');
        $limit = (int) $input->getOption('limit');
        $timeStamp = (int) $input->getArgument('timeStamp');

        try {
            $this->runner->migrate($destructive, $limit, $timeStamp);
        } catch (\Exception $e) {
            throw new MigrateException('Migration Error: "' . $e->getMessage() . '"');
        }

        $this->io->writeln('all migrations executed');
    }
}
