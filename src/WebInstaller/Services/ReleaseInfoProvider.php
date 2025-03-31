<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Composer\Util\Platform;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class ReleaseInfoProvider
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @return array<string>
     */
    public function fetchVersions(): array
    {
        /** @var array<string> $versions */
        $versions = $this->client->request('GET', 'https://releases.shopware.com/changelog/index.json')->toArray();

        usort($versions, function ($a, $b) {
            return version_compare($b, $a);
        });

        return array_values(array_filter($versions, function ($version) {
            return version_compare($version, '6.4.18.0', '>=');
        }));
    }

    /**
     * @return array<string>
     */
    public function fetchUpdateVersions(string $currentVersion): array
    {
        $nextVersion = Platform::getEnv('SW_RECOVERY_NEXT_VERSION');
        if (\is_string($nextVersion)) {
            return [
                $nextVersion,
            ];
        }

        // Get all versions newer than the current one
        $versions = array_values(array_filter($this->fetchVersions(), function ($version) use ($currentVersion) {
            return version_compare($version, $currentVersion, '>');
        }));

        // Index them by major version
        $mappedVersions = [];

        foreach ($versions as $version) {
            $major = $this->getMajor($version);

            if (!isset($mappedVersions[$major])) {
                $mappedVersions[$major] = [];
            }

            $mappedVersions[$major][] = $version;
        }

        return [
            ...$mappedVersions[$this->getNextMajor($currentVersion)] ?? [],
            ...$mappedVersions[$this->getMajor($currentVersion)] ?? [],
        ];
    }

    private function getMajor(string $version): string
    {
        $list = explode('.', $version, 3);

        return $list[0] . '.' . $list[1];
    }

    private function getNextMajor(string $version): string
    {
        $list = explode('.', $version, 3);

        ++$list[1];

        return $list[0] . '.' . $list[1];
    }
}
