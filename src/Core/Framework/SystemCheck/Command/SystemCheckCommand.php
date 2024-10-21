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
    private const OUTPUT_FORMATS = ['table', 'json'];

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

        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Change the output format.', 'table', self::OUTPUT_FORMATS);
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

        $format = $input->getOption('format');
        if (!\in_array($format, self::OUTPUT_FORMATS, true)) {
            $output->writeln(\sprintf('Invalid format provided. Allowed values are %s', implode(', ', self::OUTPUT_FORMATS)));

            return Command::INVALID;
        }

        $result = $this->systemChecker->check($context);
        $this->printOutput($input, $output, $verbose, $result, $format);

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
     * @param 'json'|'table' $format
     */
    private function printOutput(InputInterface $input, OutputInterface $output, bool $verbose, array $result, string $format): void
    {
        $io = new ShopwareStyle($input, $output);
        $headers = ['Name', 'Healthy', 'Status', 'Message', 'Extra'];

        $isJsonOutput = $format === 'json';
        $rows = array_map(
            fn (Result $result) => [
                'name' => $result->name,
                'healthy' => $result->healthy,
                'status' => $result->status->name,
                'message' => $result->message,
                'extra' => $verbose ? json_encode($result->extra, \JSON_PRETTY_PRINT) : ($isJsonOutput ? [] : null),
            ],
            $result
        );

        if ($isJsonOutput) {
            $io->write(json_encode(['checks' => $rows], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        }

        if ($format === 'table') {
            $io->table($headers, array_values($rows));
        }
    }
}
