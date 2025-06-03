<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Danger\Struct\FileCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * As we run Core Framework integrations tests in $suite batches,
 * any new test contained in a new 'domain', sub folder of $root (tests/integration/Core/Framework/),
 * first level sub folder, or single test file must be also described in phpunit.dist.xml to be run.
 *
 * This rule checks for missing integration tests in split suites:
 * - scans only the new or renamed test files from integrations, in tests/integration/Core/Framework
 * - checks their path against the elements listed in phpunit.dist.xml
 * - loads phpunit.dist.xml from PR (to allow the user to fix it on the spot)
 * - fails with a comment when the path is not found, advising which line to copy/paste in phpunit.dist.xml
 *
 * @internal
 */
#[Package('framework')]
class MissingIntegrationTestInSplitSuite
{
    public function __construct(private string $suite = 'core-framework-batch', private string $root = 'tests/integration/Core/Framework')
    {
    }

    public function __invoke(Context $context): void
    {
        $pullRequestFiles = $context->platform->pullRequest->getFiles();

        $addedTests = $pullRequestFiles
            ->filter(fn (File $file) => \in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED, File::STATUS_RENAMED], true))
            ->matches($this->root . '/**Test.php');

        if (\count($addedTests) === 0) {
            return;
        }

        $nodes = $this->getNodes($pullRequestFiles, $context);

        if (\count($nodes) === 0) {
            return;
        }

        $missing = $this->getMissing($addedTests, $nodes);
        if (\count($missing) > 0) {
            $context->failure(
                \sprintf(
                    'Please add the integration test(s) within one of the %s testsuite of phpunit.xml.dist: <br/><br/> %s',
                    $this->suite,
                    implode('<br/>', $missing)
                )
            );
        }
    }

    /**
     * @param array<string|null> $nodes
     *
     * @return array<string|null>
     */
    private function getMissing(FileCollection $addedTests, array $nodes): array
    {
        $missing = [];
        foreach ($addedTests as $file) {
            $filePath = \dirname($file->name);

            if ($filePath === $this->root) {
                $nodeType = 'file';
                $filePath = $file->name; // Full path to the file
            } else {
                $nodeType = 'directory';
                $filePath = str_replace($this->root . '/', '', $filePath);
                $filePath = explode('/', $filePath);
                $filePath = $this->root . '/' . current($filePath); // Use only the first level of the directory
            }

            $matches = array_filter($nodes, function ($item) use ($filePath) {
                if ($item === null) {
                    return false; // Skip null items
                }

                return str_contains($filePath, $item);
            });

            if (empty($matches)) { // In PHP 8.4+ we will use array_any() directly
                $missing[] = htmlentities('<' . $nodeType . '>' . $filePath . '</' . $nodeType . '>');
            }
        }

        return array_unique($missing);
    }

    /**
     * @return list<string|null>
     */
    private function getNodes(FileCollection $pullRequestFiles, Context $context): array
    {
        $nodes = [];
        $dom = new \DOMDocument();
        $phpUnitConfigFromPullRequest = $pullRequestFiles
            ->matches('phpunit.xml.dist')
            ->first();

        if (!$phpUnitConfigFromPullRequest) { // no phpunit.xml.dist file is found in the PR, we use the default one
            $phpUnitConfig = __DIR__ . '/phpunit.xml.dist';
            $domLoad = $dom->load($phpUnitConfig);
        } else {
            $phpUnitConfig = $phpUnitConfigFromPullRequest->name;
            $domLoad = $dom->loadXML($phpUnitConfigFromPullRequest->getContent());
        }

        if ($domLoad === false) {
            $context->failure(\sprintf('Was not able to load phpunit config file %s. Please check configuration.', $phpUnitConfig));

            return $nodes;
        }

        $xpath = new \DOMXPath($dom);
        $domElements = $xpath->query('//testsuite[contains(@name, "' . $this->suite . '")]/directory | //testsuite[contains(@name, "core")]/directory');
        if (!($domElements instanceof \DOMNodeList) || \count($domElements) === 0) {
            $context->failure(
                \sprintf(
                    'Was not able to find suites matching %s, in phpunit config file %s. Please check configuration.',
                    $this->suite,
                    $phpUnitConfig
                )
            );

            return $nodes;
        }

        foreach ($domElements as $element) {
            $nodes[] = $element->nodeValue;
        }

        return $nodes;
    }
}
