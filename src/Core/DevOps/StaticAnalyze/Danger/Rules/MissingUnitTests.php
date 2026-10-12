<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Every new class under `src/` needs a matching unit test, unless it is a plain data holder
 * (entity, struct, event, ...), abstract, or excluded from code coverage via the annotation or
 * the phpunit.xml.dist source excludes.
 *
 * @internal
 */
#[Package('framework')]
class MissingUnitTests
{
    private const BASE_TEST_CLASSES = [
        'RuleTestCase',
        'TestCase',
        'MiddlewareTestCase',
    ];

    private const IGNORE_SUFFIXES = [
        'Entity',
        'Collection',
        'Struct',
        'Field',
        'Test',
        'Definition',
        'Event',
        'Exception',
    ];

    public function __construct(private readonly string $phpunitConfigPath = __DIR__ . '/../../../../../../phpunit.xml.dist')
    {
    }

    public function __invoke(Context $context): void
    {
        $addedUnitTests = $context->platform->pullRequest->getFiles()
            ->filter(fn (File $file) => \in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED, File::STATUS_RENAMED], true))
            ->matches('tests/unit/**/*Test.php');

        $addedSrcFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('src/**/*.php');
        $missingUnitTests = [];
        $unitTestsName = [];

        // prepare phpunit code coverage exclude lists
        $excludedDirs = [];
        $excludedFiles = [];
        $dom = new \DOMDocument();

        $phpUnitConfigFromPullRequest = $context->platform->pullRequest->getFiles()
            ->matches('phpunit.xml.dist')
            ->first();

        $phpUnitConfigFromPullRequestContent = $phpUnitConfigFromPullRequest?->getContent();
        $phpUnitConfig = $phpUnitConfigFromPullRequest->name ?? $this->phpunitConfigPath;
        \assert($phpUnitConfig !== '');
        $domLoad = $phpUnitConfigFromPullRequestContent !== null && $phpUnitConfigFromPullRequestContent !== ''
            ? $dom->loadXML($phpUnitConfigFromPullRequestContent)
            : $dom->load($phpUnitConfig);

        if ($domLoad) {
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//source/exclude/directory') ?: [] as $dirDomElement) {
                $excludedDirs[] = [
                    'path' => rtrim((string) $dirDomElement->nodeValue, '/') . '/',
                    'suffix' => $dirDomElement instanceof \DOMElement ? $dirDomElement->getAttribute('suffix') : '',
                ];
            }

            foreach ($xpath->query('//source/exclude/file') ?: [] as $fileDomElements) {
                $excludedFiles[] = $fileDomElements->nodeValue;
            }
        } else {
            $context->warning(\sprintf('Was not able to load phpunit config file %s. Please check configuration.', $phpUnitConfig));
        }

        foreach ($addedUnitTests as $file) {
            $content = $file->getContent();

            preg_match('/\s+extends\s+(?<class>\w+)/', $content, $matches);

            if (isset($matches['class']) && \in_array($matches['class'], self::BASE_TEST_CLASSES, true)) {
                $fqcn = str_replace('.php', '', $file->name);
                $className = explode('/', $fqcn);

                $unitTestsName[] = end($className);
            }
        }

        foreach ($addedSrcFiles as $file) {
            if ($this->isExcluded($file, $excludedDirs, $excludedFiles)) {
                continue;
            }

            $fqcn = str_replace('.php', '', $file->name);
            $className = explode('/', $fqcn);
            $class = end($className);

            if (!\in_array($class . 'Test', $unitTestsName, true)) {
                $missingUnitTests[] = $file->name;
            }
        }

        if ($missingUnitTests !== []) {
            $context->failure(
                'Please be kind and add unit tests for your new code in these files: <br/><br/>'
                . implode('<br/>', $missingUnitTests)
                . '<br/> You can run `composer make:coverage` to generate dummy unit tests for files that are not covered'
            );
        }
    }

    /**
     * @param list<array{path: string, suffix: string}> $excludedDirs
     * @param list<string|null> $excludedFiles
     */
    private function isExcluded(File $file, array $excludedDirs, array $excludedFiles): bool
    {
        $content = $file->getContent();

        $fqcn = str_replace('.php', '', $file->name);
        $className = explode('/', $fqcn);
        $class = end($className);

        if (\str_contains($content, '* @codeCoverageIgnore')) {
            return true;
        }

        if (\str_contains($content, 'abstract class ' . $class)) {
            return true;
        }

        if (\str_contains($content, 'interface ' . $class)) {
            return true;
        }

        if (\str_contains($content, 'trait ' . $class)) {
            return true;
        }

        if (\str_starts_with($class, 'Migration1')) {
            return true;
        }

        // DependencyInjection service-wiring files (PHP closures using ContainerConfigurator) need no unit tests.
        if ((str_contains($file->name, '/DependencyInjection/') || preg_match('#/Resources/config/services(?:_[^/]*)?\.php$#', $file->name) === 1) && str_contains($content, 'ContainerConfigurator')) {
            return true;
        }

        // process phpunit code coverage exclude lists
        if (\in_array($file->name, $excludedFiles, true)) {
            return true;
        }

        $dir = \dirname($file->name);
        $fileName = basename($file->name);

        foreach ($excludedDirs as $excludedDir) {
            if (str_starts_with($dir . '/', $excludedDir['path']) && str_ends_with($fileName, $excludedDir['suffix'])) {
                return true;
            }
        }

        foreach (self::IGNORE_SUFFIXES as $ignoreSuffix) {
            if (\str_ends_with($class, $ignoreSuffix)) {
                return true;
            }
        }

        return false;
    }
}
