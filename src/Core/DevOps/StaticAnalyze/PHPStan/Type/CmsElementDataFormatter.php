<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type Manifest from CmsElementDataPreparation
 * @phpstan-import-type Resolver from CmsElementDataPreparation
 * @phpstan-import-type Entry from CmsElementDataCollector
 *
 * @internal
 */
#[Package('framework')]
class CmsElementDataFormatter implements ErrorFormatter
{
    /**
     * @var Manifest
     */
    private array $manifest;

    public function __construct(string $manifestFile, private readonly string $outputFile, private readonly TypeStringResolver $typeResolver)
    {
        $this->manifest = json_decode((new Filesystem())->readFile($manifestFile), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $failures = $analysisResult->getNotFileSpecificErrors();
        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            if (!$error->canBeIgnored()) {
                $failures[] = $error->getMessage();
            }
        }
        if ($analysisResult->hasInternalErrors() || ($analysisResult->getProcessedFiles() === [] && !$analysisResult->isResultCacheUsed()) || $failures !== []) {
            $output->writeLineFormatted('CMS resolver inference failed: ' . implode(\PHP_EOL, $failures));

            return 2;
        }
        $entries = [];
        foreach ($analysisResult->getCollectedData() as $collected) {
            if ($collected->getCollectorType() !== CmsElementDataCollector::class) {
                continue;
            }
            foreach ($collected->getData() as $rows) {
                foreach ($rows as $row) {
                    $entries[$row['callback']][] = $row;
                }
            }
        }
        $types = [];
        $unresolved = [];
        $unknownName = false;
        foreach ($this->manifest['resolvers'] as $resolver) {
            $names = array_unique(array_column(array_filter($entries[$resolver['type']] ?? [], static fn (array $entry): bool => $entry['kind'] === 'name'), 'value'));
            if (\count($names) !== 1 || \count($entries[$resolver['type']] ?? []) !== 1) {
                $unknownName = true;
                $unresolved[] = $resolver['class'] . ': cannot infer a single element name.';

                continue;
            }
            $data = $this->dataTypes($resolver, $resolver['enrich'], $resolver['parameter'], $entries, []);
            $name = reset($names);
            if ($data === null || $data === []) {
                $types[$name] = Struct::class;
                $unresolved[] = $resolver['class'] . ': cannot infer all mutations of the CMS slot.';
            } else {
                $types[$name] = (new Printer())->print(TypeCombinator::union(...$data)->toPhpDocNode());
            }
        }
        // An unknown name could replace any preceding registration at runtime.
        if ($unknownName) {
            $types = [];
        }
        foreach ($unresolved as $message) {
            $output->writeLineFormatted('CMS resolver inference: ' . $message);
        }
        (new Filesystem())->dumpFile($this->outputFile, json_encode(['types' => $types, 'unresolved' => $unresolved], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));

        return 0;
    }

    /**
     * @param Resolver $resolver
     * @param array<string, list<Entry>> $entries
     * @param list<string> $stack
     *
     * @return list<Type>|null
     */
    private function dataTypes(array $resolver, string $callback, string $parameter, array $entries, array $stack): ?array
    {
        $key = $callback . ':' . $parameter;
        if (!isset($this->manifest['methods'][$callback]) || !\in_array($parameter, $this->manifest['methods'][$callback]['slots'], true) || \in_array($key, $stack, true)) {
            return null;
        }
        $stack[] = $key;
        $types = [];
        foreach ($entries[$callback] ?? [] as $entry) {
            if ($entry['parameter'] !== $parameter) {
                continue;
            }
            if ($entry['kind'] === 'data') {
                $types[] = $this->typeResolver->resolve($entry['value']);
            } elseif ($entry['kind'] === 'call') {
                $target = str_starts_with($entry['value'], '$this::') ? ($resolver['methods'][substr($entry['value'], 7)] ?? '') : $entry['value'];
                $argument = ctype_digit($entry['argument']) ? ($this->manifest['methods'][$target]['parameters'][(int) $entry['argument']] ?? '') : $entry['argument'];
                $nested = $this->dataTypes($resolver, $target, $argument, $entries, $stack);
                if ($nested === null) {
                    return null;
                }
                array_push($types, ...$nested);
            } else {
                return null;
            }
        }

        return $types;
    }
}
