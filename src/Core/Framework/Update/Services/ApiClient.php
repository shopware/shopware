<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Struct\Version;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Package('system-settings')]
final class ApiClient
{
    /**
     * @internal
     */
    public function __construct(
        private HttpClientInterface $client,
        private readonly bool $shopwareUpdateEnabled
    ) {
    }

    public function checkForUpdates(): Version
    {
        if (!$this->shopwareUpdateEnabled) {
            return new Version();
        }

        /** @var array{tag_name: string, name: string, created_at: string, body: string} $gitHub */
        $gitHub = $this->client->request('GET', 'https://api.github.com/repos/shopware/platform/releases/tags/v' . $this->determineLatestShopwareVersion());

        $version = new Version();
        $version->version = $gitHub['tag_name'];
        $version->name = $gitHub['name'];
        $version->createdAt = new \DateTimeImmutable($gitHub['created_at']);
        $version->changelog = $gitHub['body'];

        return $version;
    }

    private function determineLatestShopwareVersion(): string
    {
        /** @var array{packages: array{"shopware/core": array{version: string}[]}} $response */
        $response = $this->client->request('GET', 'https://repo.packagist.org/p2/shopware/core.json')->toArray();

        $versions = array_column($response['packages']['shopware/core'], 'version');

        foreach ($versions as $version) {
            if (str_contains($version, 'dev-') || str_contains($version, 'alpha') || str_contains($version, 'beta') || str_contains($version, 'rc')) {
                continue;
            }

            return $version;
        }

        throw new \RuntimeException('Could not determine latest Shopware version');
    }
}
