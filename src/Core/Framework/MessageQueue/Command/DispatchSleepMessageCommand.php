<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Command;

use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\MessageQueue\Message\SleepMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DispatchSleepMessageCommand extends Command
{
    use ConsoleProgressTrait;

    protected static $defaultName = 'debug:messenger:dispatch-sleep';

    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        parent::__construct();

        $this->messageBus = $messageBus;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Dispatches a sleep message, which just sleeps. Can be used to debug the messenger')
            ->addArgument('time', InputArgument::OPTIONAL, 'time to sleep', '1.0')
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'message count')
            ->addOption('throw-exception', 't', InputOption::VALUE_NONE, 'dispatch failing message')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = max(1, (int) $input->getOption('count'));
        $sleepTime = (float) $input->getArgument('time');
        $throwError = $input->getOption('throw-exception');

        for ($i = 0; $i < $count; ++$i) {
            $this->messageBus->dispatch(new SleepMessage($sleepTime, $throwError));
        }

        return self::SUCCESS;
    }
}
