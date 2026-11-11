<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
final readonly class RenderEventDataFormatter implements ErrorFormatter
{
    public function __construct(private string $outputFile)
    {
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $failures = $analysisResult->getNotFileSpecificErrors();
        if ($analysisResult->getProcessedFiles() === [] && !$analysisResult->isResultCacheUsed()) {
            $failures[] = 'The render listener pass did not analyze any files.';
        }
        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            if (!$error->canBeIgnored()) {
                $failures[] = $error->getMessage();
            }
        }
        if ($analysisResult->hasInternalErrors() || $failures !== []) {
            foreach ($failures as $failure) {
                $output->writeLineFormatted($failure);
            }

            return 2;
        }

        $writes = [];
        foreach ($analysisResult->getCollectedData() as $data) {
            if ($data->getCollectorType() === RenderEventParameterCollector::class) {
                foreach ($data->getData() as $nodeWrites) {
                    array_push($writes, ...$nodeWrites);
                }
            }
        }
        usort($writes, static fn (array $left, array $right): int => [$left['file'], $left['line']] <=> [$right['file'], $right['line']]);
        (new Filesystem())->dumpFile($this->outputFile, json_encode($writes, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));

        return 0;
    }
}
