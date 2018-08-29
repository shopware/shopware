<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\Event\MigrateAdvanceEvent;
use Shopware\Core\Content\Media\Event\MigrateFinishEvent;
use Shopware\Core\Content\Media\Event\MigrateStartEvent;
use Shopware\Core\Content\Media\Migration\MediaMigration;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\StrategyFactory;
use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaMigrateCommand extends Command implements EventSubscriberInterface
{
    /**
     * @var PrefixFilesystem
     */
    private $filesystem;

    /**
     * @var StrategyFactory
     */
    private $strategyFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $event;

    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct(PrefixFilesystem $filesystem, StrategyFactory $strategyFactory, EventDispatcherInterface $event)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->strategyFactory = $strategyFactory;
        $this->event = $event;
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
        $this->io->progressStart($event->getNumberOfFiles());
    }

    public function onAdvance(MigrateAdvanceEvent $event)
    {
        $this->io->progressAdvance();
    }

    public function onFinish(MigrateFinishEvent $event)
    {
        $this->io->progressFinish();
        $this->io->table(
            ['Action', 'Number of files'],
            [
                ['Migrated', $event->getMigrated()],
                ['Skipped', $event->getSkipped()],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('media:migrate')
            ->setDescription('Migrate images to another strategy')
            ->addArgument('target-strategy', InputArgument::REQUIRED, 'Target strategy (e.g. md5, plain)')
            ->addOption('skip-scan', null, InputOption::VALUE_NONE, 'Skips the initial filesystem scan and migrates the files immediately.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $logger = new ConsoleLogger($output);

        $to = $input->getArgument('target-strategy');
        $skipScan = $input->getOption('skip-scan');

        $mediaMigration = new MediaMigration(
            $this->filesystem,
            $this->strategyFactory->factory($to),
            $this->event,
            $logger
        );

        $this->io->comment('Search for media files. This may take some time...');
        $mediaMigration->run($skipScan);
    }
}
