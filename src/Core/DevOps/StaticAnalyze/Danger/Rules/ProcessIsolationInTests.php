<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * A unit or devops test runs in the shared PHPUnit process. Process isolation re-bootstraps per test, runs outside
 * the PHPUnit extensions and hides global state the test should restore itself. Every unit or devops test file a pull
 * request adds or modifies is checked on its current content. Integration and migration tests are not checked yet.
 *
 * Only lines starting with an attribute count, so the attribute names inside string fixtures are not reported.
 *
 * @internal
 */
#[Package('framework')]
class ProcessIsolationInTests
{
    private const ISOLATION_ATTRIBUTE = '/^\s*#\[(?:[^\]]*,\s*)?\\\\?(?:PHPUnit\\\\Framework\\\\Attributes\\\\)?(RunInSeparateProcess|RunTestsInSeparateProcesses|RunClassInSeparateProcess)\b/m';

    public function __invoke(Context $context): void
    {
        $violations = [];

        foreach ($context->platform->pullRequest->getFiles() as $file) {
            if (!$this->isCheckedTestFile($file)) {
                continue;
            }

            if (preg_match_all(self::ISOLATION_ATTRIBUTE, $file->getContent(), $matches) > 0) {
                $violations[] = \sprintf('`%s` uses `%s`', $file->name, implode('`, `', array_unique($matches[1])));
            }
        }

        if ($violations !== []) {
            $context->failure(
                'A test in a separate process re-bootstraps PHPUnit and runs outside our PHPUnit extensions, so it is slow and silently loses the guards we rely on.'
                . ' Restore the global state the test changes instead:<br/>'
                . implode('<br/>', $violations)
            );
        }
    }

    private function isCheckedTestFile(File $file): bool
    {
        // rule-test fixtures deliberately contain the pattern
        return $file->status !== File::STATUS_REMOVED
            && preg_match('#^tests/(unit|devops)/.+Test\.php$#D', $file->name) === 1
            && !str_contains($file->name, '/data/');
    }
}
