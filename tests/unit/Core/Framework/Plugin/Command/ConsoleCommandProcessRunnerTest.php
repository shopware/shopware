<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\ConsoleCommandProcessRunner;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConsoleCommandProcessRunner::class)]
class ConsoleCommandProcessRunnerTest extends TestCase
{
    use EnvTestBehaviour;

    private string $projectDir;

    private ConsoleCommandProcessRunner $runner;

    private Command $command;

    protected function setUp(): void
    {
        $this->projectDir = __DIR__ . '/_fixtures/console-project';
        $this->runner = new ConsoleCommandProcessRunner($this->projectDir);

        $this->command = new Command('plugin:install');
        $this->command->addArgument('plugins', InputArgument::REQUIRED | InputArgument::IS_ARRAY);
        $this->command->addOption('activate', 'a', InputOption::VALUE_NONE);
        $this->command->addOption('reinstall', null, InputOption::VALUE_NONE);
        $this->command->addOption('user', null, InputOption::VALUE_REQUIRED);
        $this->command->addOption('language', null, InputOption::VALUE_REQUIRED, '', 'en');
        $this->command->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY);
    }

    public function testRunsTheCommandWithTheGivenArgumentsAndTheOptionsOfTheInput(): void
    {
        $input = new ArrayInput([
            'plugins' => ['Searched'],
            '--activate' => true,
            '--user' => 'admin',
            '--language' => 'de',
            '--tag' => ['first', 'second'],
        ], $this->command->getDefinition());
        $output = new BufferedOutput();

        $exitCode = $this->runner->run($this->command, $input, ['SwagFirst', 'SwagSecond'], $output);

        static::assertSame(3, $exitCode);
        static::assertSame(
            [
                'plugin:install',
                'SwagFirst',
                'SwagSecond',
                '--activate',
                '--user=admin',
                '--language=de',
                '--tag=first',
                '--tag=second',
                '--no-interaction',
            ],
            $this->getChildOutput($output)['arguments'],
        );
    }

    public function testLeavesOutOptionsThatHaveTheirDefaultValue(): void
    {
        $input = new ArrayInput(['plugins' => ['SwagFirst']], $this->command->getDefinition());
        $output = new BufferedOutput();

        $this->runner->run($this->command, $input, ['SwagFirst'], $output);

        static::assertSame(
            ['plugin:install', 'SwagFirst', '--no-interaction'],
            $this->getChildOutput($output)['arguments'],
        );
    }

    public function testRunsTheProcessInTheProjectDirectoryAndMarksItAsChildProcess(): void
    {
        $input = new ArrayInput(['plugins' => ['SwagFirst']], $this->command->getDefinition());
        $output = new BufferedOutput();

        $this->runner->run($this->command, $input, ['SwagFirst'], $output);

        $childReport = $this->getChildOutput($output);
        static::assertSame(realpath($this->projectDir), realpath($childReport['workingDirectory']));
        static::assertSame('1', $childReport['childMarker']);
    }

    public function testRunsAnotherCommandWithExactlyTheGivenArguments(): void
    {
        $output = new BufferedOutput();

        $exitCode = $this->runner->runCommand('plugin:update', ['SwagFirst'], $output);

        static::assertSame(3, $exitCode);
        static::assertSame(['plugin:update', 'SwagFirst', '--no-interaction'], $this->getChildOutput($output)['arguments']);
    }

    public function testPassesTheMemoryLimitOfTheCurrentProcessOn(): void
    {
        $previousMemoryLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '3G');

        try {
            $output = new BufferedOutput();
            $this->runner->runCommand('plugin:install', ['SwagFirst'], $output);

            static::assertSame('3G', $this->getChildOutput($output)['memoryLimit']);
        } finally {
            ini_set('memory_limit', $previousMemoryLimit);
        }
    }

    public function testTellsWhyItStartsAProcessBeforeTheProcessStarts(): void
    {
        $output = new BufferedOutput();

        $this->runner->runCommand('plugin:install', ['SwagFirst'], $output);

        $writtenText = $output->fetch();
        static::assertStringContainsString('Continuing in a new process', $writtenText);
        static::assertLessThan((int) strpos($writtenText, '{'), (int) strpos($writtenText, 'Continuing in a new process'));
    }

    public function testWritesWhatTheProcessWritesToTheErrorStreamToTheErrorOutput(): void
    {
        $errorOutput = new BufferedOutput();
        $output = static::createStub(ConsoleOutputInterface::class);
        $output->method('getErrorOutput')->willReturn($errorOutput);

        $this->runner->run(
            $this->command,
            new ArrayInput(['plugins' => ['SwagFirst']], $this->command->getDefinition()),
            ['SwagFirst'],
            $output,
        );

        static::assertSame('warning', $errorOutput->fetch());
    }

    public function testDoesNotStartAProcessInsideAChildProcess(): void
    {
        $this->setEnvVars(['SHOPWARE_PLUGIN_COMMAND_CHILD_PROCESS' => '1']);

        $this->expectExceptionObject(PluginException::composerStepRepeatedInChildProcess('plugin:install'));

        $this->runner->run(
            $this->command,
            new ArrayInput(['plugins' => ['SwagFirst']], $this->command->getDefinition()),
            ['SwagFirst'],
            new BufferedOutput(),
        );
    }

    /**
     * @return array{arguments: list<string>, childMarker: string, workingDirectory: string, memoryLimit: string}
     */
    private function getChildOutput(BufferedOutput $output): array
    {
        $writtenText = $output->fetch();
        // the process writes a warning to its error stream as well, which the buffer receives in no fixed order
        $start = (int) strpos($writtenText, '{');
        $json = substr($writtenText, $start, (int) strrpos($writtenText, '}') + 1 - $start);

        /** @var array{arguments: list<string>, childMarker: string, workingDirectory: string, memoryLimit: string} $childReport */
        $childReport = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        return $childReport;
    }
}
