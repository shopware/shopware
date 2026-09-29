<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * A test file covers exactly one class. The covered class decides which domain team owns the test
 * (and the `#[Package]` value derives from it), so several `#[CoversClass]` attributes make the
 * ownership ambiguous and usually mean several subjects share one file: split it. Applies to newly
 * added tests only; the PHPStan `CoversAttributeRule` already requires the attribute itself.
 *
 * Only attributes above the class declaration count, so `#[CoversClass]` occurrences embedded in
 * heredoc fixtures (e.g. in tests of this rule set) are not reported.
 *
 * @internal
 */
#[Package('framework')]
class SingleCoversClassInTests
{
    public function __invoke(Context $context): void
    {
        $violations = [];

        foreach ($context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('tests/**/*Test.php') as $file) {
            // rule-test fixtures deliberately contain the pattern
            if (str_contains($file->name, '/data/')) {
                continue;
            }

            $content = $file->getContent();

            $classDeclaration = preg_match('/^(?:final\s+|abstract\s+)?class\s+\w+/m', $content, $match, \PREG_OFFSET_CAPTURE)
                ? $match[0][1]
                : \strlen($content);

            // bare `CoversClass(` also catches the grouped form `#[CoversClass(...), CoversClass(...)]`
            $covered = preg_match_all('/\bCoversClass\s*\(/', substr($content, 0, $classDeclaration));

            if ($covered > 1) {
                $violations[] = \sprintf('`%s` covers %d classes', $file->name, $covered);
            }
        }

        if ($violations !== []) {
            $context->failure(
                'A test file covers exactly one class: the covered class decides which domain team owns the test.'
                . ' Split these files into one test per covered class:<br/>'
                . implode('<br/>', $violations)
            );
        }
    }
}
