<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * The PHPStan baseline may only shrink: a pull request must not add more baseline entries than
 * it removes. Skippable via the `skip-danger-phpstan-baseline` label.
 *
 * @internal
 */
#[Package('framework')]
class PhpstanBaselineGrowth
{
    private const SKIP_LABEL = 'skip-danger-phpstan-baseline';

    public function __invoke(Context $context): void
    {
        $labels = array_map('strtolower', $context->platform->pullRequest->labels);

        if (\in_array(self::SKIP_LABEL, $labels, true)) {
            return;
        }

        $phpstanBaseline = $context->platform->pullRequest->getFiles()->get('phpstan-baseline.php');
        if (!$phpstanBaseline instanceof File) {
            return;
        }

        $additions = $phpstanBaseline->additions;
        if ($additions === 0) {
            return;
        }

        $deletions = $phpstanBaseline->deletions;
        if (($deletions - $additions) < 0) {
            $context->failure(
                'It is not allowed to add new ignored PHPStan errors to the baseline. ' .
                'Only removals are allowed. Try to fix the error(s) instead. ' .
                'If this should not be possible, please add a `@phpstan-ignore` annotation to the affected line with the correct identifier and a proper comment, why a fix is not possible right now.'
            );
        }
    }
}
