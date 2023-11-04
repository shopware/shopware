<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Command;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

#[AsCommand(
    name: 'scheduled-task:run',
    description: 'Runs scheduled tasks',
)]
#[Package('core')]
class ScheduledTaskRunner extends Command
{
    private bool $shouldStop = false;

    /**
     * @internal
     */
    public function __construct(
        private readonly TaskScheduler $scheduler,
        private readonly CacheItemPoolInterface $restartSignalCachePool
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);
        $endTime = null;
        $timeLimit = (int) $input->getOption('time-limit');
        if ($timeLimit) {
            $endTime = $startTime + $timeLimit;
        }

        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            $memoryLimit = MemorySizeCalculator::convertToBytes($memoryLimit);
        }

        while (!$this->shouldStop) {
            $this->scheduler->queueScheduledTasks();

            $idleTime = $this->scheduler->getMinRunInterval() ?? 30;
            if ($endTime) {
                $remainingSeconds = $endTime - microtime(true);
                if ($remainingSeconds < $idleTime) {
                    $idleTime = $remainingSeconds;
                }
            }

            $idleTime = max(1, min((int) $idleTime, 15));

            sleep($idleTime);

            if ($this->shouldRestart($startTime)) {
                $this->shouldStop = true;
                $output->writeln(sprintf('Scheduled task runner stopped due to time limit of %ds reached', $timeLimit));
            }

            if ($endTime && $endTime < microtime(true)) {
                $this->shouldStop = true;
                $output->writeln(sprintf('Scheduled task runner stopped due to time limit of %ds reached', $timeLimit));
            }

            if ($memoryLimit && memory_get_usage() > $memoryLimit) {
                $this->shouldStop = true;
                $output->writeln(sprintf('Scheduled task runner stopped due to memory limit of %d exceeded', $memoryLimit));
            }
        }

        return Command::SUCCESS;
    }

    private function shouldRestart(float $workerStartedAt): bool
    {
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);

        if (!$cacheItem->isHit()) {
            // no restart has ever been scheduled
            return false;
        }

        return $workerStartedAt < $cacheItem->get();
    }
}
