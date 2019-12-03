<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Command;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

class ScheduledTaskRunner extends Command
{
    protected static $defaultName = 'scheduled-task:run';

    /**
     * @var TaskScheduler
     */
    private $scheduler;

    /**
     * @var bool
     */
    private $shouldStop = false;

    /**
     * @var CacheItemPoolInterface
     */
    private $restartSignalCachePool;

    public function __construct(TaskScheduler $scheduler, CacheItemPoolInterface $restartSignalCachePool)
    {
        parent::__construct();

        $this->scheduler = $scheduler;
        $this->restartSignalCachePool = $restartSignalCachePool;
    }

    protected function configure(): void
    {
        $this
            ->addOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run')
            ->setDescription('Worker that runs scheduled task.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);
        $endTime = null;
        if ($timeLimit = $input->getOption('time-limit')) {
            $endTime = $startTime + $timeLimit;
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $memoryLimit = $this->convertToBytes($memoryLimit);
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

            if ($memoryLimit && \memory_get_usage() > $memoryLimit) {
                $this->shouldStop = true;
                $output->writeln(sprintf('Scheduled task runner stopped due to memory limit of %d exceeded', $memoryLimit));
            }
        }

        return 0;
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

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = mb_strtolower($memoryLimit);
        $max = (int) mb_strtolower(ltrim($memoryLimit, '+'));

        switch (mb_substr($memoryLimit, -1)) {
            case 't': $max *= 1024;
            // no break
            case 'g': $max *= 1024;
            // no break
            case 'm': $max *= 1024;
            // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }
}
