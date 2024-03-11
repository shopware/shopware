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

        // We require symfony runtime now directly in src/Core, so we remove the max version constraint
        if (isset($composerJson['require']['symfony/runtime'])) {
            $composerJson['require']['symfony/runtime'] = '>=5';
        }

        // Lock the composer version to that major version
        $version = self::getVersion($latestVersion);

        foreach ($shopwarePackages as $shopwarePackage) {
            if (!isset($composerJson['require'][$shopwarePackage])) {
                continue;
            }

            $composerJson['require'][$shopwarePackage] = $version;
        }

        $composerJson = self::configureRepositories($composerJson);

        file_put_contents($file, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    private static function getVersion(string $latestVersion): string
    {
        $nextVersion = Platform::getEnv('SW_RECOVERY_NEXT_VERSION');
        if (\is_string($nextVersion)) {
            $nextBranch = Platform::getEnv('SW_RECOVERY_NEXT_BRANCH');
            if ($nextBranch === false) {
                $nextBranch = 'dev-trunk';
            }

            if ($nextBranch === $nextVersion) {
                return $nextBranch;
            }

            return $nextBranch . ' as ' . $nextVersion;
        }

        return $latestVersion;
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private static function configureRepositories(array $config): array
    {
        $repoString = Platform::getEnv('SW_RECOVERY_REPOSITORY');
        if (\is_string($repoString)) {
            try {
                $repo = json_decode($repoString, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return $config;
            }

            $config['repositories']['recovery'] = $repo;
        }

        return $config;
    }
}
