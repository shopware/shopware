<?php declare(strict_types=1);

namespace Shopware\Core\Services;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
readonly class AppInfo
{
    public function __construct(
        public string $name,
        public string $version,
        public string $hash,
        public string $revision,
        public string $zipUrl
    ) {
    }

    /**
     * @param array<string, mixed> $appInfo
     */
    public static function fromNameAndArray(string $appName, array $appInfo): self
    {
        if (!isset($appInfo['app-version']) || !isset($appInfo['app-hash']) || !isset($appInfo['app-revision']) || !isset($appInfo['app-zip-url'])) {
            throw ServicesException::missingAppVersionInfo();
        }

        return new AppInfo(
            $appName,
            $appInfo['app-version'],
            $appInfo['app-hash'],
            $appInfo['app-revision'],
            $appInfo['app-zip-url'],
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
        ];
    }
}
