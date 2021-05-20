<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Command;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegisterScheduledTasksCommand extends Command
{
    protected static $defaultName = 'scheduled-task:register';

    /**
     * @var TaskRegistry
     */
    private $taskRegistry;

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
