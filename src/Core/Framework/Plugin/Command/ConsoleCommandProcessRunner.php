<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A new process autoloads `vendor/` as it is now, while the parent keeps the classes it loaded before a package changed.
 *
 * @internal
 */
#[Package('framework')]
class ConsoleCommandProcessRunner
{
    private const CHILD_PROCESS_ENV = 'SHOPWARE_PLUGIN_COMMAND_CHILD_PROCESS';

    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * Passes the options of `$input` on and never asks a question. Returns the exit code of the process.
     *
     * @param list<string> $arguments
     */
    public function run(Command $command, InputInterface $input, array $arguments, OutputInterface $output): int
    {
        return $this->runCommand(
            (string) $command->getName(),
            [...$arguments, ...$this->getOptionArguments($command, $input)],
            $output
        );
    }

    /**
     * Runs another command in a process of its own, with exactly the given arguments and options.
     *
     * @param list<string> $arguments
     */
    public function runCommand(string $commandName, array $arguments, OutputInterface $output): int
    {
        // a child that needs the Composer step again would start children without end
        if (getenv(self::CHILD_PROCESS_ENV) !== false) {
            throw PluginException::composerStepRepeatedInChildProcess($commandName);
        }

        $phpBinary = (new PhpExecutableFinder())->find(false);
        if ($phpBinary === false) {
            throw PluginException::phpBinaryNotFound($commandName);
        }

        // the finder drops the `-d` settings of the parent, and the child builds assets and compiles themes
        $memoryLimit = \ini_get('memory_limit');

        (new SymfonyStyle(new ArrayInput([]), $output))->note(
            'Composer packages were required. Continuing in a new process that loads their classes.'
        );

        $process = new Process(
            [
                $phpBinary,
                ...($memoryLimit === false ? [] : ['-d', 'memory_limit=' . $memoryLimit]),
                Path::join($this->projectDir, 'bin', 'console'),
                $commandName,
                ...$arguments,
                '--no-interaction',
            ],
            $this->projectDir,
            [self::CHILD_PROCESS_ENV => '1'],
        );
        $process->setTimeout(null);

        return $process->run(static function (string $type, string $buffer) use ($output): void {
            $target = $type === Process::ERR && $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $target->write($buffer, false, OutputInterface::OUTPUT_RAW);
        });
    }

    /**
     * @return list<string>
     */
    private function getOptionArguments(Command $command, InputInterface $input): array
    {
        $optionArguments = [];
        foreach ($command->getNativeDefinition()->getOptions() as $option) {
            $value = $input->getOption($option->getName());
            if ($value === $option->getDefault()) {
                continue;
            }

            if (!$option->acceptValue()) {
                $optionArguments[] = '--' . $option->getName();

                continue;
            }

            foreach ((array) $value as $item) {
                $optionArguments[] = \sprintf('--%s=%s', $option->getName(), $item);
            }
        }

        return $optionArguments;
    }
}
