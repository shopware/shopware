<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Struct\Version;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-import-type VersionFixedVulnerabilities from \Shopware\Core\Framework\Update\Struct\Version
 */
#[Package('system-settings')]
class ApiClient
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly bool $shopwareUpdateEnabled,
        private readonly string $projectDir
    ) {
    }

    public function checkForUpdates(): Version
    {
        $fakeVersion = EnvironmentHelper::getVariable('SW_RECOVERY_NEXT_VERSION');
        if (\is_string($fakeVersion)) {
            return new Version([
                'version' => $fakeVersion,
                'title' => 'Shopware ' . $fakeVersion,
                'body' => 'This is a fake version for testing purposes',
                'date' => new \DateTimeImmutable(),
                'fixedVulnerabilities' => [],
            ]);
        }

        if (!$this->shopwareUpdateEnabled) {
            return new Version();
        }

        /** @var array{title: string, body: string, date: string, version: string, fixedVulnerabilities: VersionFixedVulnerabilities[]} $github */
        $github = $this->client->request('GET', 'https://releases.shopware.com/changelog/' . $this->determineLatestShopwareVersion() . '.json')->toArray();

        $version = new Version();
        $version->title = $github['title'];
        $version->body = $github['body'];
        $version->date = new \DateTimeImmutable($github['date']);
        $version->version = $github['version'];
        $version->fixedVulnerabilities = $github['fixedVulnerabilities'];

        return $version;
    }

    public function downloadRecoveryTool(): void
    {
        if (\is_string(EnvironmentHelper::getVariable('SW_RECOVERY_NEXT_VERSION'))) {
            return;
        }

        $content = $this->client->request('GET', 'https://github.com/shopware/web-installer/releases/latest/download/shopware-installer.phar.php')->getContent();

        file_put_contents($this->projectDir . '/public/shopware-installer.phar.php', $content);
    }

    private function determineLatestShopwareVersion(): string
    {
        /** @var non-empty-array<string> $versions */
        $versions = $this->client->request('GET', 'https://releases.shopware.com/changelog/index.json')->toArray();

        return array_shift($versions);
    }
}
