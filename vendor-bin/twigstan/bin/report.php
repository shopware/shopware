<?php declare(strict_types=1);

use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CmsElementDataPreparation;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\AnalysisCompletionGuard;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\RenderEventDataPreparation;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;

$root = dirname(__DIR__, 3);
require dirname(__DIR__) . '/bootstrap.php';
$output = new ConsoleOutput(decorated: false);
$start = hrtime(true);
$filesystem = new Filesystem();
$directory = $root . '/var/twigstan-report';
$filesystem->mkdir($directory);

try {
    CmsElementDataPreparation::prepare($root, $root . '/vendor-bin/twigstan/bin/phpstan', $root . '/vendor-bin/twigstan/bootstrap.php');
    RenderEventDataPreparation::prepare($root, $output);
    $container = ContainerFactory::fromFile($root, $root . '/twigstan.php')->create();
    $result = $container->getByType(AnalyzeCommand::class)->analyze([], $output, $output->getErrorOutput(), false, false, null);
    $failures = $result->fileSpecificErrors;
    $checked = [];
    $coveredSources = [];
    $processed = [];
    $passes = [];
    foreach ($result->runs as $run) {
        $count = 0;
        foreach ($run->scopeInjectionResults as $file => $compiled) {
            $processed[$file] = true;
            if ($run->contextBefore->hasTemplateOrMacros($file)) {
                $checked[$file] = true;
                $coveredSources[$file] = $file;
                foreach ($compiled->phpToTwigLineMapping as $location) {
                    foreach ($location as $source) {
                        $coveredSources[$source->fileName] = $file;
                    }
                }
                ++$count;
            }
        }
        $passes[] = ['pass' => $run->number, 'withContext' => $count, 'cumulative' => count($checked)];
        foreach ($run->phpstanAnalysisResults as $analysis) {
            array_push($failures, ...$analysis->notFileSpecificErrors);
            if ($analysis->exitCode > 1) {
                $failures[] = 'PHPStan exit code ' . $analysis->exitCode;
            }
        }
    }
    $templates = [];
    foreach ((new Finder())->files()->in($root . '/src/Storefront/Resources/views')->name('*.twig')->sortByName() as $file) {
        $templates[] = $file->getRealPath();
    }
    $missing = array_values(array_diff($templates, array_keys($checked)));
    $contracts = [];
    foreach ($result->context->context as $template => $renderPoints) {
        foreach ($renderPoints as [$location]) {
            if (str_ends_with($location->fileName, '/StorefrontTemplateContracts.php')) {
                $contracts[$template] = true;
            }
        }
    }
    $report = [
        'status' => $failures === [] ? 'completed' : 'failed',
        'seconds' => round((hrtime(true) - $start) / 1e9, 3),
        'templates' => count($templates),
        'checked' => count(array_intersect($templates, array_keys($checked))),
        'sourceCoverage' => count(array_intersect($templates, array_keys($coveredSources))),
        'processed' => count($processed),
        'diagnostics' => count($result->errors),
        'failures' => $failures,
        'passes' => $passes,
        'missing' => $missing,
        'uncoveredSources' => array_values(array_diff($templates, array_keys($coveredSources))),
        'inheritedSources' => array_intersect_key($coveredSources, array_flip(array_diff($templates, array_keys($checked)))),
        'declaredContracts' => array_keys($contracts),
    ];
    $filesystem->dumpFile($directory . '/coverage.json', json_encode($report, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
    $filesystem->dumpFile($directory . '/diagnostics.json', json_encode($result->errors, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
    $filesystem->dumpFile($directory . '/contexts.json', json_encode($result->context->context, \JSON_THROW_ON_ERROR));
    $filesystem->dumpFile($directory . '/summary.md', sprintf(
        "### TwigStan experimental analysis\n\n| Measurement | Result |\n| --- | ---: |\n| Storefront template coverage | %d / %d |\n| Direct contexts | %d |\n| Inherited templates | %d |\n| Declared contracts | %d |\n| Diagnostics for triage | %d |\n| Internal errors | %d |\n| Duration | %.3f seconds |\n\nDiagnostics are informational; incomplete analysis fails this job.\nThe artifact contains the findings, context sources and dependency provenance.\n",
        $report['sourceCoverage'],
        $report['templates'],
        $report['checked'],
        count($report['inheritedSources']),
        count($report['declaredContracts']),
        $report['diagnostics'],
        count($report['failures']),
        $report['seconds'],
    ));
    $filesystem->copy(dirname(__DIR__) . '/composer.lock', $directory . '/composer.lock', true);
    $filesystem->copy($root . '/var/cache/twigstan-listeners/context.json', $directory . '/listener-context.json', true);
    $filesystem->copy($root . '/var/cache/phpstan-cms/context.json', $directory . '/cms-element-context.json', true);
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR), \PHP_EOL;
    AnalysisCompletionGuard::check($report);
} catch (Throwable $exception) {
    fwrite(\STDERR, $exception . \PHP_EOL);
    exit(2);
}
