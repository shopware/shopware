<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\GenerateEntitySchemaTypesCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * The conversion spawn is intercepted through the `convertEntitySchema()` seam;
 * the real process boundary is covered by the integration test.
 */
#[Package('framework')]
#[CoversClass(GenerateEntitySchemaTypesCommand::class)]
class GenerateEntitySchemaTypesCommandTest extends TestCase
{
    private const ADMINISTRATION_ROOT = __DIR__ . '/_fixtures/administration-root';

    private const ADMINISTRATION_ROOT_WITHOUT_DEPENDENCIES = __DIR__ . '/_fixtures/administration-root-without-dependencies';

    public function testStopsWhenTheSchemaDumpFailsWithoutConverting(): void
    {
        $command = $this->recordingCommand(self::ADMINISTRATION_ROOT, convertExitCode: Command::SUCCESS);
        $this->registerInApplication($command, dumpExitCode: 3);

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(3, $exitCode, 'a failed dump short-circuits and propagates its exit code');
        static::assertNull($command->convertedRoot, 'conversion must not run when the dump failed');
    }

    public function testFailsWithNpmCiGuidanceWhenNodeDependenciesAreMissing(): void
    {
        $command = $this->recordingCommand(self::ADMINISTRATION_ROOT_WITHOUT_DEPENDENCIES, convertExitCode: Command::SUCCESS);
        $this->registerInApplication($command, dumpExitCode: Command::SUCCESS);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));
        static::assertNull($command->convertedRoot, 'conversion must not run without the Node dependencies');
    }

    public function testConvertsAfterASuccessfulDumpAndPropagatesTheExitCode(): void
    {
        $command = $this->recordingCommand(self::ADMINISTRATION_ROOT, convertExitCode: 3);
        $this->registerInApplication($command, dumpExitCode: Command::SUCCESS);

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(3, $exitCode, 'the converter exit code is propagated');
        static::assertSame(self::ADMINISTRATION_ROOT, $command->convertedRoot);
    }

    public function testAdministrationRootResolvesToTheBundleResourcesPathByDefault(): void
    {
        $command = new class extends GenerateEntitySchemaTypesCommand {
            public function exposedAdministrationRoot(): string
            {
                return $this->administrationRoot();
            }
        };

        static::assertStringEndsWith('/Resources/app/administration', $command->exposedAdministrationRoot());
    }

    public function testDumpEntitySchemaFailsWhenNoApplicationIsAvailable(): void
    {
        $command = new class extends GenerateEntitySchemaTypesCommand {
            public function exposedDumpEntitySchema(string $schemaFile, OutputInterface $output): int
            {
                return $this->dumpEntitySchema($schemaFile, $output);
            }
        };

        // Without a console Application there is no framework:schema command to delegate to.
        static::assertSame(Command::FAILURE, $command->exposedDumpEntitySchema('/tmp/schema.json', new BufferedOutput()));
    }

    public function testDumpEntitySchemaDelegatesToTheFrameworkSchemaCommand(): void
    {
        $command = new class extends GenerateEntitySchemaTypesCommand {
            public function exposedDumpEntitySchema(string $schemaFile, OutputInterface $output): int
            {
                return $this->dumpEntitySchema($schemaFile, $output);
            }
        };

        $schemaCommand = new class extends Command {
            public ?string $outfile = null;

            public ?string $schemaFormat = null;

            protected function configure(): void
            {
                $this->setName('framework:schema')
                    ->addArgument('outfile')
                    ->addOption('schema-format', null, InputOption::VALUE_REQUIRED);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->outfile = $input->getArgument('outfile');
                $this->schemaFormat = $input->getOption('schema-format');

                return 4;
            }
        };

        $application = new Application();
        $application->addCommand($schemaCommand);
        $command->setApplication($application);

        $exitCode = $command->exposedDumpEntitySchema('/tmp/entity-schema.json', new BufferedOutput());

        static::assertSame(4, $exitCode, 'the framework:schema exit code is propagated');
        static::assertSame('/tmp/entity-schema.json', $schemaCommand->outfile);
        static::assertSame('entity-schema', $schemaCommand->schemaFormat);
    }

    /**
     * @return GenerateEntitySchemaTypesCommand&object{convertedRoot: string|null}
     */
    private function recordingCommand(string $administrationRoot, int $convertExitCode): GenerateEntitySchemaTypesCommand
    {
        return new class($administrationRoot, $convertExitCode) extends GenerateEntitySchemaTypesCommand {
            public ?string $convertedRoot = null;

            public function __construct(string $administrationRoot, private readonly int $convertExitCode)
            {
                parent::__construct($administrationRoot);
            }

            protected function convertEntitySchema(string $administrationRoot, OutputInterface $output): int
            {
                $this->convertedRoot = $administrationRoot;

                return $this->convertExitCode;
            }
        };
    }

    /**
     * Registers the command in an Application alongside a fake `framework:schema`,
     * so `execute()` runs end-to-end (the schema dump delegates to the fake).
     */
    private function registerInApplication(GenerateEntitySchemaTypesCommand $command, int $dumpExitCode): void
    {
        $schemaCommand = new class($dumpExitCode) extends Command {
            public function __construct(private readonly int $dumpExitCode)
            {
                parent::__construct('framework:schema');
            }

            protected function configure(): void
            {
                $this->addArgument('outfile')
                    ->addOption('schema-format', null, InputOption::VALUE_REQUIRED);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return $this->dumpExitCode;
            }
        };

        $application = new Application();
        $application->addCommand($schemaCommand);
        $command->setApplication($application);
    }
}
