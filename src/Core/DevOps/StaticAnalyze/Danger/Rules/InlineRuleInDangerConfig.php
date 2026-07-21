<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * `.danger.php` must stay declarative: rules live as classes in
 * src/Core/DevOps/StaticAnalyze/Danger/Rules (where they get unit tests and PHPStan coverage),
 * not as closures or anonymous classes inlined into the config.
 *
 * @internal
 */
#[Package('framework')]
class InlineRuleInDangerConfig
{
    /**
     * Matches added lines that pass anything but a `new ClassName(...)` to useRule() (closures,
     * arrow functions, variables, anonymous classes), or that define a closure or anonymous
     * class anywhere in the config.
     */
    private const INLINE_RULE_PATTERN = '/^\+.*(?:->useRule\([^\S\n]*(?!new\s+[A-Z\\\\])\S|\bfunction\s*\(|\bfn\s*\(|\bnew\s+class\b)/m';

    public function __invoke(Context $context): void
    {
        $inlineRules = $context->platform->pullRequest->getFiles()
            ->filter(static fn (File $file): bool => $file->name === '.danger.php'
                && preg_match(self::INLINE_RULE_PATTERN, $file->patch) === 1);

        if ($inlineRules->count() <= 0) {
            return;
        }

        $context->failure(
            'Please do not define Danger rules inline in `.danger.php`. Implement the rule as a class in'
            . ' `src/Core/DevOps/StaticAnalyze/Danger/Rules` with a unit test, and register it via'
            . ' `->useRule(new MyRule())`.'
        );
    }
}
