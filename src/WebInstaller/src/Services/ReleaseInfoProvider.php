<?php
declare(strict_types=1);

namespace App\Services;

use Composer\Util\Platform;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('core')]
class ReleaseInfoProvider
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @return array<string, string>
     */
    public function fetchLatestRelease(bool $includeRCReleases = false): array
    {
        $nextVersion = Platform::getEnv('SW_RECOVERY_NEXT_VERSION');
        if (\is_string($nextVersion)) {
            return [
                '6.4' => '6.4.17.2',
                '6.5' => $nextVersion,
            ];
        }

        /** @var array<string> $response */
        $versions = $this->client->request('GET', 'https://releases.shopware.com/changelog/index.json')->toArray();

        usort($versions, function ($a, $b) {
            return version_compare($b, $a);
        });

        // Index them by major version
        $mappedVersions = [];

        foreach ($versions as $version) {
            if (str_contains($version, 'rc') && !$includeRCReleases) {
                continue;
            }

            $major = substr($version, 0, 3);

            if (isset($mappedVersions[$major])) {
                continue;
            }

            $mappedVersions[$major] = $version;
        }

        return $mappedVersions;
    }
}
