<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class FeatureFlagGuardTest extends TestCase
{
    public function testLegacyGuardsSurviveLookupsAndAliasesWithoutCoveringOtherCalls(): void
    {
        $root = \dirname(__DIR__, 6);
        $fixtures = $root . '/tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/FeatureGuardedDeprecationExtension';
        $filesystem = new Filesystem();
        $directory = $filesystem->tempnam(sys_get_temp_dir(), 'phpstan-features-');
        $filesystem->remove($directory);
        $filesystem->mkdir($directory);
        try {
            $results = [];
            $legacyCalls = [];
            $valueUsages = [];
            $callbackCalls = [];
            foreach (['before', 'after'] as $name) {
                $config = $directory . '/' . $name . '.neon';
                $include = json_encode($fixtures . '/' . $name . '.neon', \JSON_THROW_ON_ERROR);
                $cache = json_encode($directory . '/cache-' . $name, \JSON_THROW_ON_ERROR);
                $filesystem->dumpFile($config, "includes:\n    - $include\nparameters:\n    tmpDir: $cache\n");
                // The project bootstrap can leave Symfony's inherited console verbosity in quiet mode.
                $process = new Process([\PHP_BINARY, $root . '/vendor/bin/phpstan', 'analyse', '--autoload-file=' . $root . '/vendor/autoload.php', '--configuration=' . $config, '--no-progress', '--error-format=json'], $root, env: ['SHELL_VERBOSITY' => '0'], timeout: 120);
                $process->run();
                static::assertSame(1, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
                static::assertJson($process->getOutput(), $process->getErrorOutput() . $process->getOutput());
                $result = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
                static::assertSame([], $result['errors']);
                $results[$name] = array_column($result['files'][$fixtures . '/Api.php']['messages'], 'identifier', 'line');
                $legacyCalls[$name] = array_column($result['files'][$fixtures . '/LegacyApi.php']['messages'], 'identifier', 'line');
                $valueUsages[$name] = $result['files'][$fixtures . '/LegacyValues.php']['messages'];
                $callbackCalls[$name] = $result['files'][$fixtures . '/InactiveCallbacks.php']['messages'];
            }

            static::assertCount(10, $results['before']);
            static::assertSame([38 => 'method.deprecated', 41 => 'method.deprecated', 43 => 'method.deprecated', 45 => 'method.deprecated', 48 => 'method.deprecated', 51 => 'method.deprecated', 64 => 'method.deprecated', 66 => 'method.deprecated'], $results['after']);
            static::assertCount(4, $legacyCalls['before']);
            static::assertSame([28 => 'staticMethod.deprecatedClass', 30 => 'new.deprecatedClass'], $legacyCalls['after']);
            static::assertCount(19, $valueUsages['before']);
            // Keep the parameter-type diagnostic and all nine unguarded executable uses.
            static::assertSame([$valueUsages['before'][0], ...\array_slice($valueUsages['before'], 10)], $valueUsages['after']);
            static::assertCount(4, $callbackCalls['before']);
            static::assertSame(\array_slice($callbackCalls['before'], 2), $callbackCalls['after']);
        } finally {
            $filesystem->remove($directory);
        }
    }
}
