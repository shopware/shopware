<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Tests must not invoke private or protected methods of Shopware classes via reflection: it couples
 * the test to implementation details instead of behaviour. Test through the public API, or
 * restructure the production code (e.g. extract the logic into a collaborator) so it is publicly
 * testable. Reflecting into a third-party class is a different matter and stays acceptable when a
 * vendor API leaves no other option.
 *
 * This is a textual gate on the added lines of changed test files, so it sees neither the target
 * class nor the method's visibility: the line that names the class is often not even part of the
 * same patch. It therefore keys on reflective *invocation* (`->invoke()`, `->invokeArgs()`,
 * `setAccessible()`) and reports a warning rather than a failure, so a legitimate case — a
 * third-party target, or a public method — can be resolved by a human instead of blocking the
 * pull request. Constructing a reflection object to read metadata (a declaring class, a signature,
 * attributes) is not reported at all. The precise, visibility- and namespace-aware counterpart is
 * the PHPStan rule `shopware.reflectionOnNonPublicMethod`.
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

        $context->warning(
            'These test files invoke a method via reflection (`->invoke()`, `->invokeArgs()`, `->setAccessible()`).'
            . ' If the target is a private or protected method of a Shopware class, test the behaviour through the'
            . ' public API instead, or restructure the code (e.g. extract the logic into a collaborator) so it is'
            . ' publicly testable. `setAccessible()` has no effect since PHP 8.1.<br/>'
            . 'Resolve this thread if the reflection targets a third-party class, where no public alternative exists,'
            . ' or a public method. This check reads the diff only, so it cannot see the target class itself.<br/>'
            . implode('<br/>', $fileNames)
        );
    }
}
