<?php
declare(strict_types=1);

namespace App\Services;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('core')]
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
        /** @var string $fileName */
        $fileName = $_SERVER['SCRIPT_FILENAME'];

        return \dirname($fileName);
    }

    public function getShopwareLocation(): string
    {
        $projectDir = $this->getProjectDir();

        $composerLookups = [
            \dirname($projectDir) . '/composer.lock',
            $projectDir . '/composer.lock',
            $projectDir . '/shopware/composer.lock',
        ];

        foreach ($composerLookups as $composerLookup) {
            if (file_exists($composerLookup)) {
                /** @var array{packages: array{name: string, version: string}[]} $composerJson */
                $composerJson = json_decode((string) file_get_contents($composerLookup), true, \JSON_THROW_ON_ERROR);

                foreach ($composerJson['packages'] as $package) {
                    if ($package['name'] === 'shopware/core' || $package['name'] === 'shopware/platform') {
                        return \dirname($composerLookup);
                    }
                }
            }
        }

        throw new \RuntimeException('Could not find Shopware installation');
    }

    public function getCurrentShopwareVersion(string $shopwarePath): string
    {
        $lockFile = $shopwarePath . '/composer.lock';

        if (!file_exists($lockFile)) {
            throw new \RuntimeException('Could not find composer.lock file');
        }

        /** @var array{packages: array{name: string, version: string}[]} $composerLock */
        $composerLock = json_decode((string) file_get_contents($lockFile), true, \JSON_THROW_ON_ERROR);

        foreach ($composerLock['packages'] as $package) {
            if ($package['name'] === 'shopware/core' || $package['name'] === 'shopware/platform') {
                return $package['version'];
            }
        }

        throw new \RuntimeException('Could not find shopware package in the composer.lock');
    }

    public function isFlexProject(string $shopwarePath): bool
    {
        return file_exists($shopwarePath . '/symfony.lock');
    }
}
