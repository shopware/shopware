<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Command;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package system-settings
 */
#[AsCommand(
    name: 'scheduled-task:register',
    description: 'Registers all scheduled tasks',
)]
class RegisterScheduledTasksCommand extends Command
{
    /**
     * @var TaskRegistry
     */
    private $taskRegistry;

    /**
     * @internal
     */
    public function __construct(TaskRegistry $taskRegistry)
    {
        parent::__construct();

        $this->taskRegistry = $taskRegistry;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Registers all available scheduled tasks.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Registering scheduled tasks ...');
        $this->taskRegistry->registerTasks();
        $output->writeln('Done!');

        return self::SUCCESS;
    }
}
