<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\GenerateEntitySchemaTypesCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
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
