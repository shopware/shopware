<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Content\Media\Commands;

use Shopware\Content\Media\Event\MigrateAdvanceEvent;
use Shopware\Content\Media\Event\MigrateFinishEvent;
use Shopware\Content\Media\Event\MigrateStartEvent;
use Shopware\Content\Media\Util\MediaMigration;
use Shopware\Content\Media\Util\Strategy\StrategyFactory;
use Shopware\Content\Media\Util\Strategy\StrategyFilesystem;
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
     * @var \Shopware\Content\Media\Util\Strategy\StrategyFilesystem
     */
    private $filesystem;

    /**
     * @var \Shopware\Content\Media\Util\Strategy\StrategyFactory
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

    public function __construct(StrategyFilesystem $filesystem, StrategyFactory $strategyFactory, EventDispatcherInterface $event)
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
            $this->filesystem->getAdapter(),
            $this->strategyFactory->factory($to),
            $this->event,
            $logger
        );

        $this->io->comment('Search for media files. This may take some time...');
        $mediaMigration->run($skipScan);
    }
}
