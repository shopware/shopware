<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Tests must not invoke private or protected methods via reflection: it couples the test to
 * implementation details instead of behaviour. Test through the public API, or restructure the
 * production code (e.g. extract the logic into a collaborator) so it is publicly testable.
 *
 * Fast textual gate on the added lines of changed test files. Because it reads the diff rather than
 * the type graph, it cannot resolve a method's visibility, so it keys on reflective *invocation*
 * instead: `->invoke()`, `->invokeArgs()` and `setAccessible()`. Constructing a reflection object to
 * read metadata (a declaring class, a signature, attributes) is untouched and stays allowed at any
 * visibility. Invoking a public method reflectively would also be flagged, which is fine, because
 * calling it directly is always available. The visibility-aware counterpart is the PHPStan rule
 * `shopware.reflectionOnNonPublicMethod`.
 *
 * @internal
 */
#[Package('framework')]
class ReflectionOnPrivateMethodsInTests
{
    private const ADDED_REFLECTION_PATTERN = '/^\+.*(->invoke\s*\(|->invokeArgs\s*\(|->setAccessible\s*\()/m';

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
            'Tests must not invoke methods via reflection (`->invoke()`, `->invokeArgs()`, `->setAccessible()`).'
            . ' For a private or protected method, test the behaviour through the public API instead, or restructure'
            . ' the code (e.g. extract the logic into a collaborator) so it is publicly testable. For a public method,'
            . ' just call it. `setAccessible()` has no effect since PHP 8.1. Reading metadata from a reflection object,'
            . ' such as a declaring class, a signature or attributes, is allowed and not reported here.<br/>'
            . implode('<br/>', $fileNames)
        );
    }
}
