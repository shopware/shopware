<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * The PHPStan baselines may only shrink: a pull request must not add more baseline entries than
 * it removes. Skippable via the `skip-danger-phpstan-baseline` label.
 *
 * @internal
 */
#[Package('framework')]
class PhpstanBaselineGrowth
{
    private const SKIP_LABEL = 'skip-danger-phpstan-baseline';

    private const BASELINES = [
        'phpstan-baseline.php',
        'phpstan-baseline-architecture.php',
    ];

    public function __invoke(Context $context): void
    {
        $labels = array_map('strtolower', $context->platform->pullRequest->labels);

        if (\in_array(self::SKIP_LABEL, $labels, true)) {
            return;
        }

        foreach (self::BASELINES as $baselineName) {
            $baseline = $context->platform->pullRequest->getFiles()->get($baselineName);
            if (!$baseline instanceof File || $baseline->deletions - $baseline->additions >= 0) {
                continue;
            }

            $context->failure(
                \sprintf('It is not allowed to add new ignored PHPStan errors to `%s`. ', $baselineName) .
                'Only removals are allowed. Try to fix the error(s) instead. ' .
                'If this should not be possible, please add a `@phpstan-ignore` annotation to the affected line with the correct identifier and a proper comment, why a fix is not possible right now.'
            );
        }
    }
}
