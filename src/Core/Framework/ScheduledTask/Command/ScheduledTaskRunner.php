<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask\Command;

use Shopware\Core\Framework\ScheduledTask\Scheduler\TaskScheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduledTaskRunner extends Command
{
    /**
     * @var TaskScheduler
     */
    private $scheduler;

    /**
     * @var bool
     */
    private $shouldStop = false;

    public function __construct(TaskScheduler $scheduler)
    {
        parent::__construct();

        $this->scheduler = $scheduler;
    }

    protected function configure(): void
    {
        $this->setName('scheduled-task:run')
            ->addOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run')
            ->setDescription('Worker that runs scheduled task.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
                    $idleTime = (int) $remainingSeconds;
                }
            }

            sleep($idleTime);

            if ($endTime && $endTime < microtime(true)) {
                $this->shouldStop = true;
                $output->writeln(sprintf('Scheduled task runner stopped due to time limit of %ds reached', $timeLimit));
            }

            if ($memoryLimit && \memory_get_usage() > $memoryLimit) {
                $this->shouldStop = true;
                $output->writeln(sprintf('Scheduled task runner stopped due to memory limit of %d exceeded', $memoryLimit));
            }
        }
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = (int) strtolower(ltrim($memoryLimit, '+'));

        switch (substr($memoryLimit, -1)) {
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
