<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\GenerateEntitySchemaTypesCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Spawns the schema converter for real: its exit code has to survive the process boundary.
 * The two-step orchestration is covered by the unit test through the `convertEntitySchema()` seam.
 *
 * @internal
 */
#[Package('framework')]
class GenerateEntitySchemaTypesCommandTest extends TestCase
{
    private string $administrationRoot;

    protected function setUp(): void
    {
        $this->administrationRoot = ExtensionToolingFixture::createAdministrationRoot(withToolingStub: true, stubExitCode: 3);
    }

    protected function tearDown(): void
    {
        ExtensionToolingFixture::removeAdministrationRoot($this->administrationRoot);
    }

    public function testConvertsAfterASuccessfulDumpAndPropagatesTheExitCode(): void
    {
        $command = new GenerateEntitySchemaTypesCommand($this->administrationRoot);

        // The schema dump delegates to framework:schema, which is faked here so only the converter spawns.
        $schemaCommand = new class extends Command {
            public function __construct()
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
                return Command::SUCCESS;
            }
        };

        $application = new Application();
        $application->addCommand($schemaCommand);
        $application->addCommand($command);

        $exitCode = (new CommandTester($command))->execute([]);

        static::assertSame(3, $exitCode, 'the converter exit code is propagated');

        $capture = ExtensionToolingFixture::readToolingCapture($this->administrationRoot);
        static::assertSame(realpath($this->administrationRoot), $capture['cwd']);
        static::assertContains('--transpileOnly', $capture['argv']);
        static::assertStringEndsWith('scripts/entitySchemaConverter/convert-schema.ts', $capture['argv'][1]);
    }
}
