<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefreshIndexCommand extends Command implements EventSubscriberInterface
{
    /**
     * @var SymfonyStyle|null
     */
    private $io;

    /**
     * @var IndexerInterface
     */
    private $indexer;

    public function __construct(IndexerInterface $indexer)
    {
        parent::__construct('dbal:refresh:index');
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProgressStartedEvent::NAME => 'startProgress',
            ProgressAdvancedEvent::NAME => 'advanceProgress',
            ProgressFinishedEvent::NAME => 'finishProgress',
        ];
    }

    public function finishProgress(ProgressFinishedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressFinish();
        $this->io->success($event->getMessage());
    }

    public function startProgress(ProgressStartedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->comment($event->getMessage());
        $this->io->progressStart($event->getTotal());
    }

    public function advanceProgress(ProgressAdvancedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressAdvance($event->getStep());
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dbal:refresh:index')
            ->setDescription('Refreshes the shop indices');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $this->indexer->index(new \DateTime(), $tenantId);
    }
}
