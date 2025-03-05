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
        public ?string $hashAlgorithm = null,
        public ?string $minShopSupportedVersion = null
    ) {
    }

    /**
     * @param array<string, mixed> $appInfo
     */
    public static function fromNameAndArray(string $appName, array $appInfo): self
    {
        if (!isset($appInfo['app-version']) || !isset($appInfo['app-hash']) || !isset($appInfo['app-revision']) || !isset($appInfo['app-zip-url'])) {
            throw ServiceException::missingAppVersionInfo();
        }

        return new AppInfo(
            $appName,
            $appInfo['app-version'],
            $appInfo['app-hash'],
            $appInfo['app-revision'],
            $appInfo['app-zip-url'],
            $appInfo['app-hash-algorithm'] ?? null,
            $appInfo['app-min-shop-supported-version'] ?? null,
        );
    }

    /**
     * @return array{version: string, hash: string, revision: string, zip-url: string, hash-algorithm: string|null, min-shop-supported-version: string|null}
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
