<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Boyscouting for the PHPStan baseline: whoever touches a file with baselined errors is asked
 * to also fix those errors. Skippable via the `skip-danger-phpstan-baseline` label.
 *
 * @internal
 */
#[Package('framework')]
class IgnoredPhpstanErrorsInTouchedFiles
{
    private const SKIP_LABEL = 'skip-danger-phpstan-baseline';

    public function __invoke(Context $context): void
    {
        $labels = array_map('strtolower', $context->platform->pullRequest->labels);

        if (\in_array(self::SKIP_LABEL, $labels, true)) {
            return;
        }

        $filesWithIgnoredErrors = [];
        $phpstanBaseline = $context->platform->pullRequest->getFile('phpstan-baseline.php')->getContent();
        foreach ($context->platform->pullRequest->getFiles()->map(fn (File $f) => $f->name) as $fileName) {
            if (str_contains($phpstanBaseline, $fileName)) {
                $filesWithIgnoredErrors[] = $fileName;
            }
        }

        if ($filesWithIgnoredErrors) {
            $context->failure(
                'Some files you touched in your MR contain ignored PHPStan errors. Please be nice and fix all ignored errors for the following files:<br>'
                . implode('<br>', $filesWithIgnoredErrors)
            );
        }
    }
}
