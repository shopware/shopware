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
 */
#[Package('framework')]
#[CoversClass(GenerateEntitySchemaTypesCommand::class)]
class GenerateEntitySchemaTypesCommandTest extends TestCase
{
    use ExtensionToolingCommandTestBehaviour;

    public function testStopsWhenTheSchemaDumpFailsWithoutConverting(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: true);
        $command = $this->commandInApplication($administrationRoot, dumpExitCode: 3);

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(3, $exitCode, 'a failed dump short-circuits and propagates its exit code');
        static::assertFileDoesNotExist(
            $administrationRoot . '/.tooling-capture.json',
            'conversion must not run when the dump failed',
        );

        $this->removeAdministrationRoot($administrationRoot);
    }

    public function testFailsWithNpmCiGuidanceWhenNodeDependenciesAreMissing(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: false);
        $command = $this->commandInApplication($administrationRoot, dumpExitCode: Command::SUCCESS);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));
        static::assertFileDoesNotExist(
            $administrationRoot . '/.tooling-capture.json',
            'conversion must not run without the Node dependencies',
        );

        $this->removeAdministrationRoot($administrationRoot);
    }

    public function testConvertsAfterASuccessfulDumpAndPropagatesTheExitCode(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: true, stubExitCode: 3);
        $command = $this->commandInApplication($administrationRoot, dumpExitCode: Command::SUCCESS);

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(3, $exitCode, 'the converter exit code is propagated');

        $capture = $this->readToolingCapture($administrationRoot);
        static::assertStringEndsWith('scripts/entitySchemaConverter/convert-schema.ts', $capture['argv'][1]);

        $this->removeAdministrationRoot($administrationRoot);
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
     * Registers the real command in an Application alongside a fake `framework:schema`,
     * so `execute()` runs end-to-end on the real class (the schema dump delegates to
     * the fake, the conversion spawns the injected root's `ts-node` stub).
     */
    private function commandInApplication(string $administrationRoot, int $dumpExitCode): GenerateEntitySchemaTypesCommand
    {
        $command = new GenerateEntitySchemaTypesCommand($administrationRoot);

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
        $application->addCommand($command);

        return $command;
    }
}
