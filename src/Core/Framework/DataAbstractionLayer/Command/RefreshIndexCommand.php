<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
     * @var IndexerRegistryInterface
     */
    private $indexer;

    /**
     * @var ProgressBar|null
     */
    private $progress;

    public function __construct(IndexerRegistryInterface $indexer)
    {
        parent::__construct('dal:refresh:index');
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProgressStartedEvent::NAME => 'startProgress',
            ProgressAdvancedEvent::NAME => 'advanceProgress',
            ProgressFinishedEvent::NAME => 'finishProgress',
        ];
    }

    public function startProgress(ProgressStartedEvent $event)
    {
        if (!$this->io) {
            return;
        }

        $this->progress = $this->io->createProgressBar($event->getTotal());
        $this->progress->setFormat("<info>[%message%]</info>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $this->progress->setMessage($event->getMessage());
    }

    public function advanceProgress(ProgressAdvancedEvent $event)
    {
        if (!$this->progress) {
            return;
        }

        $this->progress->advance($event->getStep());
    }

    public function finishProgress(ProgressFinishedEvent $event)
    {
        if (!$this->progress) {
            return;
        }
        if (!$this->progress->getMaxSteps()) {
            return;
        }
        $this->progress->setMessage($event->getMessage());
        $this->progress->finish();
        $this->io->newLine(2);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dal:refresh:index')
            ->setDescription('Refreshes the shop indices');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ShopwareStyle($input, $output);

        $this->indexer->index(new \DateTime());
    }
}
