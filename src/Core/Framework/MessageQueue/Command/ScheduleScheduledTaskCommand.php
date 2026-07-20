<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'scheduled-task:schedule',
    description: 'Schedule a scheduled task',
)]
#[Package('framework')]
class ScheduleScheduledTaskCommand extends Command
{
    private const STATUSES_SUCCESS = [
        ScheduledTaskDefinition::STATUS_SCHEDULED,
        ScheduledTaskDefinition::STATUS_SKIPPED,
    ];

    private const STATUSES_FORCE_NEEDED = [
        ScheduledTaskDefinition::STATUS_QUEUED,
        ScheduledTaskDefinition::STATUS_RUNNING,
    ];

    /**
     * @internal
     */
    public function __construct(private readonly TaskRegistry $taskRegistry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('taskName', InputArgument::REQUIRED, 'Scheduled task name like log_entry.cleanup');
        $this->addOption('force', 'f', null, 'Force the scheduling of the scheduled task');
        $this->addOption('immediately', 'i', null, 'Set the next execution time to now');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $taskName = $input->getArgument('taskName');
        $immediately = (bool) $input->getOption('immediately');
        $force = (bool) $input->getOption('force');

        $status = $this->taskRegistry->scheduleTask($taskName, $immediately, $force, Context::createCLIContext());

        if (\in_array($status, self::STATUSES_SUCCESS, true)) {
            $io->success('Scheduled task "' . $taskName . '" was scheduled' . ($immediately ? ' to now' : '') . '.');

            return self::SUCCESS;
        }

        if (\in_array($status, self::STATUSES_FORCE_NEEDED, true)) {
            $io->warning('Scheduled task "' . $taskName . '" is marked as currently running, use --force to force scheduling.');

            return self::FAILURE;
        }

        $io->error('Could not schedule task "' . $taskName . '", task has unexpected state: ' . $status);

        return self::FAILURE;
    }
}
