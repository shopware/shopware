<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class AppInfo
{
    public function __construct(
        public string $name,
        public string $version,
        public string $hash,
        public string $revision,
        public string $zipUrl,
        public string $hashAlgorithm,
        public string $minShopSupportedVersion
    ) {
    }

    /**
     * @param array<string, mixed> $appInfo
     */
    public static function fromNameAndArray(string $appName, array $appInfo): self
    {
        $requiredKeys = ['app-version', 'app-hash', 'app-revision', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'];
        foreach ($requiredKeys as $key) {
            if (!isset($appInfo[$key])) {
                throw ServiceException::missingAppVersionInfo($key);
            }
        }

        return new AppInfo(
            $appName,
            $appInfo['app-version'],
            $appInfo['app-hash'],
            $appInfo['app-revision'],
            $appInfo['app-zip-url'],
            $appInfo['app-hash-algorithm'],
            $appInfo['app-min-shop-supported-version']
        );
    }

    /**
     * @return array{version: string, hash: string, revision: string, zip-url: string}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'hash' => $this->hash,
            'revision' => $this->revision,
            'zip-url' => $this->zipUrl,
            'hash-algorithm' => $this->hashAlgorithm,
            'min-shop-supported-version' => $this->minShopSupportedVersion,
        ];
    }
}
