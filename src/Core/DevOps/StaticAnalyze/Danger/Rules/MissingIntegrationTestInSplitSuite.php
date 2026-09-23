<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * The Core Framework integration tests run in split testsuite batches, each listing its
 * directories/files in phpunit.xml.dist explicitly. A test in a directory no batch lists is
 * silently never executed — this rule fails when a new test's path is not covered.
 *
 * @internal
 */
#[Package('framework')]
class MissingIntegrationTestInSplitSuite
{
    private const ROOT = 'tests/integration/Core/Framework';

    public function __construct(private readonly string $phpunitConfigPath = __DIR__ . '/../../../../../../phpunit.xml.dist')
    {
    }

    public function __invoke(Context $context): void
    {
        $pullRequestFiles = $context->platform->pullRequest->getFiles();

        $addedTests = $pullRequestFiles
            ->filter(fn (File $file) => \in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED, File::STATUS_RENAMED], true))
            ->matches(self::ROOT . '/**Test.php');

        if (\count($addedTests) === 0) {
            return;
        }

        $dom = new \DOMDocument();
        $phpUnitConfigFromPullRequest = $pullRequestFiles
            ->matches('phpunit.xml.dist')
            ->first();

        $phpUnitConfigFromPullRequestContent = $phpUnitConfigFromPullRequest?->getContent();
        $phpUnitConfig = $phpUnitConfigFromPullRequest->name ?? $this->phpunitConfigPath;
        \assert($phpUnitConfig !== '');
        $domLoad = $phpUnitConfigFromPullRequestContent !== null && $phpUnitConfigFromPullRequestContent !== ''
            ? $dom->loadXML($phpUnitConfigFromPullRequestContent)
            : $dom->load($phpUnitConfig);

        if ($domLoad === false) {
            $context->failure(\sprintf('Was not able to load phpunit config file %s. Please check configuration.', $phpUnitConfig));

            return;
        }

        $nodes = $missing = [];

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//testsuite[contains(@name, "core-framework")]/directory | //testsuite[contains(@name, "core")]/file') ?: [] as $dirDomElement) {
            $nodes[] = $dirDomElement->nodeValue;
        }

        foreach ($addedTests as $file) {
            $filePath = \dirname($file->name);

            if ($filePath === self::ROOT) {
                $nodeType = 'file';
                $filePath = $file->name;
            } else {
                $nodeType = 'directory';
                $filePath = str_replace(self::ROOT . '/', '', $filePath);
                $filePath = explode('/', $filePath);
                $filePath = self::ROOT . '/' . current($filePath);
            }

            $matches = array_filter($nodes, function ($item) use ($filePath) {
                return str_contains($filePath, (string) $item);
            });
            if ($matches === []) {
                $missing[] = htmlentities('<' . $nodeType . '>' . $filePath . '</' . $nodeType . '>');
            }
        }

        if ($missing !== []) {
            $context->failure(
                'Please add the integration test(s) within one of the core-batch testsuite of phpunit.xml.dist: <br/><br/>'
                . implode('<br/>', array_unique($missing))
            );
        }
    }
}
