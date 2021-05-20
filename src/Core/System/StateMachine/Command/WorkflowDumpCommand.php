<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Util\StateMachineGraphvizDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkflowDumpCommand extends Command
{
    protected static $defaultName = 'state-machine:dump';

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(StateMachineRegistry $stateMachineRegistry)
    {
        parent::__construct();
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'A state machine name'),
                new InputOption('label', 'l', InputOption::VALUE_REQUIRED, 'Labels a graph'),
            ])
            ->setDescription('Dump a workflow')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command dumps the graphical representation of a
workflow in different formats

<info>DOT</info>:  %command.full_name% <state machine name> | dot -Tpng > workflow.png

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workflowName = $input->getArgument('name');
        $context = Context::createDefaultContext();
        $stateMachine = $this->stateMachineRegistry->getStateMachine($workflowName, $context);

        $dumper = new StateMachineGraphvizDumper();

        $options = [
            'name' => $stateMachine->getName(),
            'nofooter' => true,
            'graph' => [
                'label' => $input->getOption('label'),
            ],
        ];
        $output->writeln($dumper->dump($stateMachine, $options));

        return self::SUCCESS;
    }
}
