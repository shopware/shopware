<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type ServiceSourceConfig from ServiceSourceResolver
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
        public ?string $minShopwareSupportedVersion = null,
    ) {
    }

    /**
     * @param array<string, mixed> $appInfo
     */
    public static function fromRegistryResponse(string $appName, array $appInfo): self
    {
        $requiredKeys = ['app-version', 'app-hash', 'app-revision', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'];
        $missingKeys = [];
        foreach ($requiredKeys as $key) {
            if (!isset($appInfo[$key])) {
                $missingKeys[] = $key;
            }
        }

        if (!empty($missingKeys)) {
            throw ServiceException::missingAppVersionInformation(...$missingKeys);
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
     * @param ServiceSourceConfig $sourceConfig
     */
    public static function fromNameAndSourceConfig(string $appName, array $sourceConfig): self
    {
        return new AppInfo(
            $appName,
            $sourceConfig['version'],
            $sourceConfig['hash'],
            $sourceConfig['revision'],
            $sourceConfig['zip-url'],
            $sourceConfig['hash-algorithm'] ?? null,
            $sourceConfig['min-shop-supported-version'] ?? null,
        );
    }

    /**
     * @return ServiceSourceConfig
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'hash' => $this->hash,
            'revision' => $this->revision,
            'zip-url' => $this->zipUrl,
            'hash-algorithm' => $this->hashAlgorithm,
            'min-shop-supported-version' => $this->minShopwareSupportedVersion,
        ];
    }
}
