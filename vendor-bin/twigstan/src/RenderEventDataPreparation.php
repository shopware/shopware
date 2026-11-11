<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Nette\Neon\Neon;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @phpstan-import-type Write from RenderEventParameterCollector
 *
 * @internal
 */
#[Package('framework')]
final class RenderEventDataPreparation
{
    public static function prepare(string $root, OutputInterface $output): void
    {
        $listeners = RenderEventListeners::discover($root . '/var/cache/static_phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml');
        $filesystem = new Filesystem();
        $directory = $root . '/var/cache/twigstan-listeners';
        $manifest = $directory . '/listeners.json';
        $result = $directory . '/writes.json';
        $context = $directory . '/context.json';
        $filesystem->mkdir($directory);
        $filesystem->remove([$result, $context]);
        $filesystem->dumpFile($manifest, json_encode($listeners, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
        $writes = [];
        if ($listeners !== []) {
            $config = [
                'parameters' => [
                    'level' => null,
                    'customRulesetUsed' => true,
                    // This pass collects types, so inline ignores for the project's other rules need not match.
                    'reportUnmatchedIgnoredErrors' => false,
                    'paths' => array_values(array_unique(array_column($listeners, 'file'))),
                    'tmpDir' => $directory . '/phpstan',
                    'bootstrapFiles' => [$root . '/vendor-bin/twigstan/bootstrap.php'],
                    'symfony' => [
                        'constantHassers' => false,
                        'containerXmlPath' => $root . '/var/cache/static_phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml',
                    ],
                    'parallel' => ['maximumNumberOfProcesses' => 1],
                ],
                'services' => [
                    [
                        'class' => RenderEventParameterCollector::class,
                        'arguments' => ['manifestFile' => $manifest],
                        'tags' => ['phpstan.collector', 'phpstan.parser.richParserNodeVisitor'],
                    ],
                    'errorFormatter.renderEventData' => [
                        'class' => RenderEventDataFormatter::class,
                        'arguments' => ['outputFile' => $result],
                    ],
                ],
            ];
            $filesystem->dumpFile($directory . '/phpstan.neon', Neon::encode($config, true));
            $output->writeln(\sprintf('Inferring render-event data from %d registered listeners...', \count($listeners)));
            $process = new Process([\PHP_BINARY, $root . '/vendor-bin/twigstan/bin/phpstan', 'analyse', '--configuration=' . $directory . '/phpstan.neon', '--error-format=renderEventData', '--no-progress'], $root, timeout: null);
            $process->mustRun(static function (string $type, string $buffer) use ($output): void {
                $output->write($buffer);
            });
            /** @var list<Write> $writes */
            $writes = json_decode($filesystem->readFile($result), true, flags: \JSON_THROW_ON_ERROR);
        }

        $unresolved = array_values(array_filter($writes, static fn (array $write): bool => $write['kind'] === 'unresolved'));
        foreach ($unresolved as $write) {
            $output->writeln(\sprintf('Render listener inference: %s:%d: %s', $write['file'], $write['line'], $write['reason']));
        }
        $filesystem->dumpFile($context, json_encode(['listeners' => $listeners, 'writes' => $writes, 'unresolved' => $unresolved], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
    }
}
