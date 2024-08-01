<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore all the underlying dependencies are tested.
 */
#[AsCommand(name: 'system:check', description: 'Check the shopware application system health')]
#[Package('core')]
class SystemCheckCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemChecker $systemChecker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'context',
            'c',
            InputOption::VALUE_REQUIRED,
            'Context for the health check',
            SystemCheckExecutionContext::CLI->value,
            $this->getAllowedContexts()
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contextInput = $input->getOption('context');
        $verbose = $input->getOption('verbose');
        $context = SystemCheckExecutionContext::tryFrom($contextInput);
        if ($context === null) {
            $output->writeln(
                \sprintf('Invalid context provided. Allowed values are %s', implode(', ', $this->getAllowedContexts()))
            );

            return Command::INVALID;
        }

        $result = $this->systemChecker->check($context);

        $this->printOutput($input, $output, $verbose, $result);

        foreach ($result as $check) {
            if ($check->healthy !== true) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getAllowedContexts(): array
    {
        return array_map(fn (SystemCheckExecutionContext $context) => $context->value, SystemCheckExecutionContext::longRunning());
    }

    /**
     * @param array<Result> $result
     */
    private function printOutput(InputInterface $input, OutputInterface $output, bool $verbose, array $result): void
    {
        $io = new ShopwareStyle($input, $output);
        $headers = ['Name', 'Healthy', 'Status', 'Message', 'Extra'];
        $rows = array_map(
            fn (Result $result) => [
                $result->name,
                $result->healthy,
                $result->status->name,
                $result->message,
                $verbose ? json_encode($result->extra, \JSON_PRETTY_PRINT) : null,
            ],
            $result
        );

        $io->table($headers, $rows);
    }
}
