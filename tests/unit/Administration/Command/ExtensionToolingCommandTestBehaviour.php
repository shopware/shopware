<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * Builds a disposable Administration app root with a fake `ts-node` executable so
 * the commands can be driven end-to-end on their real class (which is what makes
 * `execute()`/`configure()` coverage attributable — an anonymous subclass driven
 * through the console runner records none). The stub records how it was invoked
 * and exits with a chosen code, standing in for the real toolchain.
 */
#[Package('framework')]
trait ExtensionToolingCommandTestBehaviour
{
    private function kernel(): KernelInterface
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/shop');

        return $kernel;
    }

    private function createAdministrationRoot(bool $withToolingStub, int $stubExitCode = 0): string
    {
        $root = sys_get_temp_dir() . '/' . uniqid('sw-admin-tooling-', true);
        (new Filesystem())->mkdir($root);

        if ($withToolingStub) {
            $this->writeToolingStub($root, $stubExitCode);
        }

        return $root;
    }

    private function removeAdministrationRoot(string $root): void
    {
        (new Filesystem())->remove($root);
    }

    private function writeToolingStub(string $root, int $exitCode = 0): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($root . '/node_modules/.bin');
        $filesystem->dumpFile(
            $root . '/node_modules/.bin/ts-node',
            '#!' . \PHP_BINARY . "\n"
            . "<?php\n"
            . "file_put_contents(__DIR__ . '/../../.tooling-capture.json', json_encode([\n"
            . "    'cwd' => getcwd(),\n"
            . "    'project_root' => getenv('PROJECT_ROOT'),\n"
            . "    'argv' => array_slice(\$argv, 1),\n"
            . "]));\n"
            . "fwrite(STDOUT, 'tooling-ran');\n"
            . "exit({$exitCode});\n",
        );
        $filesystem->chmod($root . '/node_modules/.bin/ts-node', 0755);
    }

    /**
     * `project_root` is `false` when the command spawns the tooling without a
     * PROJECT_ROOT env (the entity-schema generator does), otherwise the value set.
     *
     * @return array{cwd: string, project_root: string|false, argv: list<string>}
     */
    private function readToolingCapture(string $root): array
    {
        $file = $root . '/.tooling-capture.json';
        static::assertFileExists($file, 'the tooling stub was expected to run');

        $decoded = json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        $cwd = $decoded['cwd'] ?? null;
        $argv = $decoded['argv'] ?? null;
        static::assertIsString($cwd);
        static::assertIsArray($argv);

        /** @var string|false $projectRoot */
        $projectRoot = $decoded['project_root'] ?? false;
        /** @var list<string> $argv */

        return ['cwd' => $cwd, 'project_root' => $projectRoot, 'argv' => $argv];
    }
}
