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
    name: 'scheduled-task:deactivate',
    description: 'Deactivate a scheduled task',
)]
#[Package('framework')]
class DeactivateScheduledTaskCommand extends Command
{
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
        $this->addOption('force', 'f', null, 'Force deactivation of schedule task');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $io->warning('Be aware that this command will not cancel a running task execution (e.g. via the queue worker), only disable its scheduling.');

        $taskName = $input->getArgument('taskName');
        $force = $input->getOption('force');

        $status = $this->taskRegistry->deactivateTask($taskName, $force, Context::createCLIContext());

        switch ($status) {
            case ScheduledTaskDefinition::STATUS_INACTIVE:
                $io->success('Scheduled task "' . $taskName . '" was deactivated.');

                return self::SUCCESS;
            case ScheduledTaskDefinition::STATUS_QUEUED:
            case ScheduledTaskDefinition::STATUS_RUNNING:
                $io->warning('Scheduled task "' . $taskName . '" is marked as currently running, use --force to force deactivation.');

                return self::FAILURE;
            case ScheduledTaskDefinition::STATUS_FAILED:
            case ScheduledTaskDefinition::STATUS_SCHEDULED:
            case ScheduledTaskDefinition::STATUS_SKIPPED:
                $io->error('Scheduled task "' . $taskName . '" could not be deactivated.');

                return self::FAILURE;
        }

        $io->error('Scheduled task "' . $taskName . '" was set to an unknown state: ' . $status);

        return self::FAILURE;
    }
}
