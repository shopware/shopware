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
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GenerateEntitySchemaTypesCommand::class)]
class GenerateEntitySchemaTypesCommandTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    public function testStopsWhenTheSchemaDumpFailsWithoutConverting(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withDependencies: true);
        $converted = false;

        $command = $this->command($administrationRoot, dump: static fn (): int => 3, convert: static function () use (&$converted): int {
            $converted = true;

            return 0;
        });

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(3, $exitCode, 'a failed dump short-circuits and propagates its exit code');
        static::assertFalse($converted, 'conversion must not run when the dump failed');

        $this->filesystem->remove($administrationRoot);
    }

    public function testFailsWithNpmCiGuidanceWhenNodeDependenciesAreMissing(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withDependencies: false);
        $converted = false;

        $command = $this->command($administrationRoot, dump: static fn (): int => Command::SUCCESS, convert: static function () use (&$converted): int {
            $converted = true;

            return 0;
        });
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertFalse($converted, 'conversion must not run without the Node dependencies');
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));

        $this->filesystem->remove($administrationRoot);
    }

    public function testConvertsAfterASuccessfulDumpAndPropagatesTheExitCode(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withDependencies: true);
        $convertedRoot = null;

        $command = $this->command(
            $administrationRoot,
            dump: static fn (): int => Command::SUCCESS,
            convert: static function (string $root) use (&$convertedRoot): int {
                $convertedRoot = $root;

                return 1;
            },
        );

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(1, $exitCode, 'the converter exit code is propagated');
        static::assertSame($administrationRoot, $convertedRoot);

        $this->filesystem->remove($administrationRoot);
    }

    public function testAdministrationRootResolvesToTheBundleResourcesPath(): void
    {
        $root = $this->exposedCommand()->exposedAdministrationRoot();

        static::assertStringEndsWith('/Resources/app/administration', $root);
    }

    public function testDumpEntitySchemaFailsWhenNoApplicationIsAvailable(): void
    {
        // Without a console Application there is no framework:schema command to delegate to.
        $exitCode = $this->exposedCommand()->exposedDumpEntitySchema('/tmp/schema.json', new BufferedOutput());

        static::assertSame(Command::FAILURE, $exitCode);
    }

    public function testDumpEntitySchemaDelegatesToTheFrameworkSchemaCommand(): void
    {
        $command = $this->exposedCommand();

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
        $application->add($schemaCommand);
        $command->setApplication($application);

        $exitCode = $command->exposedDumpEntitySchema('/tmp/entity-schema.json', new BufferedOutput());

        static::assertSame(4, $exitCode, 'the framework:schema exit code is propagated');
        static::assertSame('/tmp/entity-schema.json', $schemaCommand->outfile);
        static::assertSame('entity-schema', $schemaCommand->schemaFormat);
    }

    public function testConvertEntitySchemaSpawnsTheConverterAndPropagatesItsExitCode(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withDependencies: true);
        // Replace the ts-node stub with an executable that reports a known exit code and output.
        $this->filesystem->dumpFile(
            $administrationRoot . '/node_modules/.bin/ts-node',
            "#!/bin/sh\nprintf 'converted'\nexit 3\n",
        );
        $this->filesystem->chmod($administrationRoot . '/node_modules/.bin/ts-node', 0755);

        $output = new BufferedOutput();
        $exitCode = $this->exposedCommand()->exposedConvertEntitySchema($administrationRoot, $output);

        static::assertSame(3, $exitCode, 'the converter exit code reaches the shell unchanged');
        static::assertStringContainsString('converted', $output->fetch());

        $this->filesystem->remove($administrationRoot);
    }

    private function createAdministrationRoot(bool $withDependencies): string
    {
        $root = sys_get_temp_dir() . '/' . uniqid('sw-admin-schema-', true);
        $this->filesystem->mkdir($root);

        if ($withDependencies) {
            $this->filesystem->mkdir($root . '/node_modules/.bin');
            $this->filesystem->touch($root . '/node_modules/.bin/ts-node');
        }

        return $root;
    }

    /**
     * A command whose protected seams (reflection root, schema dump, converter spawn) are exposed
     * so their real bodies can be exercised without going through the full execute() pipeline.
     */
    private function exposedCommand(): GenerateEntitySchemaTypesCommand
    {
        return new class extends GenerateEntitySchemaTypesCommand {
            public function exposedAdministrationRoot(): string
            {
                return $this->administrationRoot();
            }

            public function exposedDumpEntitySchema(string $schemaFile, OutputInterface $output): int
            {
                return $this->dumpEntitySchema($schemaFile, $output);
            }

            public function exposedConvertEntitySchema(string $administrationRoot, OutputInterface $output): int
            {
                return $this->convertEntitySchema($administrationRoot, $output);
            }
        };
    }

    /**
     * @param \Closure(string): int $dump
     * @param \Closure(string): int $convert
     */
    private function command(string $administrationRoot, \Closure $dump, \Closure $convert): GenerateEntitySchemaTypesCommand
    {
        return new class($administrationRoot, $dump, $convert) extends GenerateEntitySchemaTypesCommand {
            /**
             * @param \Closure(string): int $dump
             * @param \Closure(string): int $convert
             */
            public function __construct(
                private readonly string $administrationRootOverride,
                private readonly \Closure $dump,
                private readonly \Closure $convert,
            ) {
                parent::__construct();
            }

            protected function administrationRoot(): string
            {
                return $this->administrationRootOverride;
            }

            protected function dumpEntitySchema(string $schemaFile, OutputInterface $output): int
            {
                return ($this->dump)($schemaFile);
            }

            protected function convertEntitySchema(string $administrationRoot, OutputInterface $output): int
            {
                return ($this->convert)($administrationRoot);
            }
        };
    }
}
