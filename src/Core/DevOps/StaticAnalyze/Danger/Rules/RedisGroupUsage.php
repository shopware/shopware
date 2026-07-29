<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Redis tests are selected by the "redis" testsuite in phpunit.xml.dist (a static file list), not by a
 * PHPUnit group: the nightly redis job runs `--testsuite redis` so it only discovers those files instead
 * of the whole test tree. A `#[Group('redis')]` attribute has no effect there — the test would silently
 * never run against a real Redis.
 *
 * @internal
 */
#[Package('framework')]
class RedisGroupUsage
{
    public function __invoke(Context $context): void
    {
        $offenders = $context->platform->pullRequest->getFiles()
            ->filter(static fn (File $file): bool => $file->status !== File::STATUS_REMOVED
                && str_starts_with($file->name, 'tests/')
                && preg_match('/^\+.*#\[Group\(\'redis\'\)\]/m', $file->patch) === 1);

        if ($offenders->count() <= 0) {
            return;
        }

        $errorFiles = [];
        foreach ($offenders as $file) {
            $errorFiles[] = $file->name . '<br/>';
        }

        $context->failure(
            'Redis tests are selected via the `redis` testsuite in `phpunit.xml.dist`, not via `#[Group(\'redis\')]`'
            . ' — the nightly redis job only discovers the files listed in that suite, so the attribute would'
            . ' silently exclude your test from it. Remove the attribute and add your test file to the suite instead.<br/>'
            . implode('<br>', $errorFiles)
        );
    }
}
