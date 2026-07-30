<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * Bridges the Administration extension tooling — ts-node scripts shipped inside
 * the Administration package — to bin/console, so it is usable from a Composer
 * install where the Administration lives below vendor/ and the platform-only
 * Composer scripts do not exist. The Administration app root is resolved from the
 * bundle class location (layout independent), the project root from the kernel;
 * the tooling is spawned with PROJECT_ROOT set and passes it on.
 *
 * The exit code of the tooling reaches the shell unchanged: 0 ok, 1 findings,
 * 2 usage error, 3 tool error.
 */
#[Package('framework')]
abstract class AbstractExtensionToolingCommand extends Command
{
    /**
     * @internal
     *
     * @param string|null $administrationRootPath overrides the bundle-resolved app root; null resolves it automatically
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?string $administrationRootPath = null,
    ) {
        parent::__construct();
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

            return 3;
        }

        $command = [
            $tsNode,
            '--transpileOnly',
            $administrationRoot . '/scripts/extensionTooling/' . $this->entryScript(),
            ...$this->toolingArguments($input),
        ];

        return $this->runTooling($command, $administrationRoot, ['PROJECT_ROOT' => $this->kernel->getProjectDir()], $output);
    }

    /**
     * The ts-node entry script below scripts/extensionTooling, e.g. "cli.ts".
     */
    abstract protected function entryScript(): string;

    /**
     * The arguments forwarded to the tooling, translated from the Symfony input.
     *
     * @return list<string>
     */
    abstract protected function toolingArguments(InputInterface $input): array;

    /**
     * The Administration app root, resolved from the bundle class location so it
     * works in the platform monorepo and in a vendor/ install alike.
     */
    protected function administrationRoot(): string
    {
        return $this->administrationRootPath
            ?? \dirname((string) (new \ReflectionClass(Administration::class))->getFileName()) . '/Resources/app/administration';
    }

    /**
     * Spawns the tooling, streaming its output, and returns its exit code
     * verbatim. Overridable so tests can assert the invocation without spawning.
     *
     * @param list<string> $command
     * @param array<string, string> $env
     */
    protected function runTooling(array $command, string $cwd, array $env, OutputInterface $output): int
    {
        // No timeout: type-checking a large extension legitimately runs for minutes.
        $process = new Process($command, $cwd, $env, null, null);

        return $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
    }
}
