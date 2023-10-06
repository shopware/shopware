<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Composer\Util\Platform;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ProjectComposerJsonUpdater
{
    public static function update(string $file, string $latestVersion): void
    {
        $shopwarePackages = [
            'shopware/core',
            'shopware/administration',
            'shopware/storefront',
            'shopware/elasticsearch',
        ];

        /** @var array{minimum-stability?: string, require: array<string, string>} $composerJson */
        $composerJson = json_decode((string) file_get_contents($file), true, \JSON_THROW_ON_ERROR);

        if (str_contains(strtolower($latestVersion), 'rc')) {
            $composerJson['minimum-stability'] = 'RC';
        } else {
            unset($composerJson['minimum-stability']);
        }

        foreach ($shopwarePackages as $shopwarePackage) {
            if (!isset($composerJson['require'][$shopwarePackage])) {
                continue;
            }

            // Lock the composer version to that major version
            $version = $latestVersion;

            $nextVersion = Platform::getEnv('SW_RECOVERY_NEXT_VERSION');
            if (\is_string($nextVersion)) {
                $nextBranch = Platform::getEnv('SW_RECOVERY_NEXT_BRANCH');
                if ($nextBranch === false) {
                    $nextBranch = 'dev-trunk';
                }

                if ($nextBranch === $nextVersion) {
                    $version = $nextBranch;
                } else {
                    $version = $nextBranch . ' as ' . $nextVersion;
                }
            }

            $composerJson['require'][$shopwarePackage] = $version;
        }

        file_put_contents($file, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
}
