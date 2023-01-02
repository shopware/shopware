<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('core')]
trait ConsoleProgressTrait
{
    /**
     * @var SymfonyStyle|null
     */
    protected $io;

    /**
     * @var ProgressBar|null
     */
    protected $progress;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProgressStartedEvent::NAME => 'startProgress',
            ProgressAdvancedEvent::NAME => 'advanceProgress',
            ProgressFinishedEvent::NAME => 'finishProgress',
        ];
    }

    public function startProgress(ProgressStartedEvent $event): void
    {
        if (!$this->io) {
            return;
        }

        $this->progress = $this->io->createProgressBar($event->getTotal());
        $this->progress->setFormat("<info>[%message%]</info>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $this->progress->setMessage($event->getMessage());
    }

    public function advanceProgress(ProgressAdvancedEvent $event): void
    {
        if (!$this->progress) {
            return;
        }

        $this->progress->advance($event->getStep());
    }

    public function finishProgress(ProgressFinishedEvent $event): void
    {
        if (!$this->io) {
            return;
        }

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
}
