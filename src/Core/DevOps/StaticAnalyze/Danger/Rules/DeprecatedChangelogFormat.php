<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * The per-change markdown files under `changelog/_unreleased/` were replaced by the central
 * release-info and upgrade files.
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedChangelogFormat
{
    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->matches('changelog/_unreleased/*.md')->count() > 0) {
            $context->failure('The Pull Request makes use of the old changelog format. Please document your changes in the `RELEASE_INFO-6.7.md` and `UPGRADE-6.8.md` file respectively. For detailed infos please refer to the [release documentation guide](https://github.com/shopware/shopware/blob/trunk/delivery-process/documenting-a-release.md).');
        }
    }
}
