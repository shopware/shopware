<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Reminds authors to document externally relevant changes in the central release-info file.
 *
 * @internal
 */
#[Package('framework')]
class MissingReleaseInfo
{
    public function __construct(private readonly string $releaseInfoFile = 'RELEASE_INFO-6.7.md')
    {
    }

    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->count() > 0 && $this->isNeverExternallyRelevant($files->getKeys())) {
            return;
        }

        if ($files->matches($this->releaseInfoFile)->count() === 0) {
            $context->warning('The Pull Request doesn\'t contain any release info, if your changes are relevant for external developers please add an entry to the release info file, including the consequences of the change and how it affects external developers. For detailed infos please refer to the [release documentation guide](https://github.com/shopware/shopware/blob/trunk/delivery-process/documenting-a-release.md).');
        }
    }

    /**
     * Changes confined to the test suites or the static-analysis tooling are never relevant
     * for external developers.
     *
     * @param array<string|int> $fileNames
     */
    private function isNeverExternallyRelevant(array $fileNames): bool
    {
        foreach ($fileNames as $fileName) {
            $isIrrelevant = match (true) {
                str_starts_with((string) $fileName, 'tests/'),
                str_starts_with((string) $fileName, 'src/Core/DevOps/StaticAnalyze/') => true,
                default => false,
            };

            if (!$isIrrelevant) {
                return false;
            }
        }

        return true;
    }
}
