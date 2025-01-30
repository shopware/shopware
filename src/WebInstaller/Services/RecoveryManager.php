<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\WebInstaller\InstallerException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class RecoveryManager
{
    public function getBinary(): string
    {
        /** @var string $fileName */
        $fileName = $_SERVER['SCRIPT_FILENAME'];

        return $fileName;
    }

    public function getPHPBinary(Request $request): string
    {
        $phpBinary = $request->getSession()->get('phpBinary');
        \assert(\is_string($phpBinary));

        return $phpBinary;
    }

    public function getProjectDir(): string
    {
        $fileName = realpath($_SERVER['SCRIPT_FILENAME']);
        \assert(\is_string($fileName));

        return \dirname($fileName);
    }

    public function getShopwareLocation(): string
    {
        $projectDir = $this->getProjectDir();

        $composerLookup = \dirname($projectDir) . '/composer.lock';

        // The Shopware installation is always in the "public" directory
        if (basename($projectDir) !== 'public') {
            throw InstallerException::cannotFindShopwareInstallation();
        }

        if (file_exists($composerLookup)) {
            /** @var array{packages: array{name: string, version: string}[]} $composerJson */
            $composerJson = json_decode((string) file_get_contents($composerLookup), true, \JSON_THROW_ON_ERROR);

            foreach ($composerJson['packages'] as $package) {
                if ($package['name'] === 'shopware/core' || $package['name'] === 'shopware/platform') {
                    return \dirname($composerLookup);
                }
            }
        }

        throw InstallerException::cannotFindShopwareInstallation();
    }

    public function getCurrentShopwareVersion(string $shopwarePath): string
    {
        $lockFile = $shopwarePath . '/composer.lock';

        if (!file_exists($lockFile)) {
            throw InstallerException::cannotFindComposerLock();
        }

        /** @var array{packages: array{name: string, version: string}[]} $composerLock */
        $composerLock = json_decode((string) file_get_contents($lockFile), true, \JSON_THROW_ON_ERROR);

        foreach ($composerLock['packages'] as $package) {
            if ($package['name'] === 'shopware/core' || $package['name'] === 'shopware/platform') {
                return ltrim($package['version'], 'v');
            }
        }

        throw InstallerException::cannotFindShopwareInComposerLock();
    }

    public function isFlexProject(string $shopwarePath): bool
    {
        return file_exists($shopwarePath . '/symfony.lock');
    }
}
