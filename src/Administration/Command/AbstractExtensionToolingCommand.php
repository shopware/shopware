<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * Bridges the Administration extension-tooling CLI — a ts-node script shipped
 * inside the Administration package — to `bin/console`, so it is usable from a
 * Composer/Flex install where the Administration lives under vendor/ and the
 * platform-only composer scripts do not exist. The Administration app root is
 * resolved from the bundle class location (layout-independent) and the project
 * root from the kernel; the tooling is then spawned with PROJECT_ROOT set.
 */
#[Package('framework')]
abstract class AbstractExtensionToolingCommand extends Command
{
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'tooling-args',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Options forwarded to the tooling, placed after "--", e.g. -- --only=MyPlugin --all.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $administrationRoot = $this->administrationRoot();
        $tsNode = $administrationRoot . '/node_modules/.bin/ts-node';

        if (!is_file($tsNode)) {
            $io->error([
                \sprintf('The Administration\'s Node dependencies are not installed (%s is missing).', $tsNode),
                \sprintf('Run "npm ci" in %s first, then re-run this command.', $administrationRoot),
            ]);

            return Command::FAILURE;
        }

        /** @var list<string> $forwardedArguments */
        $forwardedArguments = $input->getArgument('tooling-args');

        $command = [
            $tsNode,
            '--transpileOnly',
            $administrationRoot . '/scripts/extensionTooling/' . $this->toolingEntryScript(),
            ...$forwardedArguments,
        ];

        return $this->runTooling($command, $administrationRoot, ['PROJECT_ROOT' => $this->kernel->getProjectDir()], $output);
    }

    /**
     * The ts-node entry script under scripts/extensionTooling, e.g. "check.ts".
     */
    abstract protected function toolingEntryScript(): string;

    /**
     * The Administration app root, resolved from the bundle class location so it
     * works in the platform monorepo and in a vendor/ install alike.
     */
    protected function administrationRoot(): string
    {
        return \dirname((string) (new \ReflectionClass(Administration::class))->getFileName())
            . '/Resources/app/administration';
    }

    /**
     * Spawns the tooling, streaming its output, and returns its exit code so the
     * tooling's own exit semantics (0 ok, 1 findings/drift, 2 usage) reach the
     * shell. Overridable so tests can assert the invocation without spawning.
     *
     * @param list<string> $command
     * @param array<string, string> $env
     */
    protected function runTooling(array $command, string $cwd, array $env, OutputInterface $output): int
    {
        // No timeout: type-checking a large plugin legitimately runs for minutes.
        $process = new Process($command, $cwd, $env, null, null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
    }
}
