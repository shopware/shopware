<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Tests must not invoke non-public methods via reflection: it couples the test to implementation
 * details instead of behaviour. Test through the public API, or restructure the production code
 * (e.g. extract the logic into a collaborator) so it is publicly testable.
 *
 * Fast textual gate on the added lines of changed test files.
 *
 * @internal
 */
#[Package('framework')]
class ReflectionOnPrivateMethodsInTests
{
    private const ADDED_REFLECTION_PATTERN = '/^\+.*(new\s+\\\\?ReflectionMethod\s*\(|ReflectionClass\([^)]*\)\)->getMethod\s*\(|->setAccessible\s*\()/m';

    public function __invoke(Context $context): void
    {
        $offendingFiles = $context->platform->pullRequest->getFiles()
            ->filter(static function (File $file): bool {
                if (!\in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED], true)) {
                    return false;
                }

                if (!fnmatch('tests/**/*Test.php', $file->name)
                    && !fnmatch('src/**/*Test.php', $file->name)) {
                    return false;
                }

                // rule-test fixtures deliberately contain the pattern
                if (str_contains($file->name, '/data/')) {
                    return false;
                }

                return preg_match(self::ADDED_REFLECTION_PATTERN, $file->patch) === 1;
            });

        if ($offendingFiles->count() <= 0) {
            return;
        }

        $fileNames = [];
        foreach ($offendingFiles as $file) {
            $fileNames[] = $file->name;
        }

        $context->failure(
            'Tests must not use reflection to access non-public methods'
            . ' (`new \ReflectionMethod(...)` on a private/protected method, `->getMethod(...)`, `->setAccessible(...)`).'
            . ' Test the behaviour through the public API, or restructure the code (e.g. extract the logic into a'
            . ' collaborator) so it is publicly testable. `setAccessible()` has no effect since PHP 8.1.<br/>'
            . implode('<br/>', $fileNames)
        );
    }
}
