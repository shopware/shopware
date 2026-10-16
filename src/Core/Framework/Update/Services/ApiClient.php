<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Psr\Clock\ClockInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\ReleaseEndpointsEnum;
use Shopware\Core\Framework\Update\Struct\Version;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-import-type VersionFixedVulnerabilities from Version
 */
/**
 * @phpstan-import-type VersionFixedVulnerabilities from Version
 */
#[Package('framework')]
class ApiClient
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $shopwareVersion,
        private readonly string $projectDir,
        private readonly ClockInterface $clock
    ) {
    }

    public function checkForUpdates(): Version
    {
        return $this->getFakeVersion() ?? ($this->fetchVersionChangelog($this->determineLatestVersion(ReleaseEndpointsEnum::RELEASE)) ?? new Version());
    }

    public function checkForSecurityUpdates(): Version
    {
        return $this->getFakeVersion() ?? ($this->fetchVersionChangelog($this->determineLatestVersion(ReleaseEndpointsEnum::SECURITY)) ?? new Version());
    }

    public function downloadRecoveryTool(): void
    {
        if (\is_string(EnvironmentHelper::getVariable('SW_RECOVERY_NEXT_VERSION'))) {
            return;
        }

        $content = $this->client->request('GET', 'https://github.com/shopware/web-installer/releases/latest/download/shopware-installer.phar.php')->getContent();

        file_put_contents($this->projectDir . '/public/shopware-installer.phar.php', $content);
    }

    private function determineLatestVersion(ReleaseEndpointsEnum $endpoint): string
    {
        try {
            /** @var list<string> $versions */
            $versions = $this->client->request('GET', $endpoint->value)->toArray();
        } catch (ClientException $e) {
            if ($e->getCode() === Response::HTTP_NOT_FOUND || $e->getCode() === Response::HTTP_FORBIDDEN) {
                return '';
            }

            throw $e;
        }

        if ($versions === []) {
            return '';
        }

        return $this->resolveLatestVersion($versions);
    }

    private function getFakeVersion(): ?Version
    {
        $fakeVersion = EnvironmentHelper::getVariable('SW_RECOVERY_NEXT_VERSION');
        if (!\is_string($fakeVersion)) {
            return null;
        }

        $version = new Version();
        $version->version = $fakeVersion;
        $version->title = 'Shopware ' . $fakeVersion;
        $version->body = 'This is a fake version for testing purposes';
        $version->date = $this->clock->now();
        $version->fixedVulnerabilities = [];

        return $version;
    }

    private function fetchVersionChangelog(string $version): ?Version
    {
        if ($version === '') {
            return null;
        }

        try {
            /** @var array{title: string, body: string, date: string, version: string, fixedVulnerabilities: VersionFixedVulnerabilities[]} $github */
            $github = $this->client->request('GET', 'https://releases.shopware.com/changelog/' . $version . '.json')->toArray();
        } catch (ClientException $e) {
            if ($e->getCode() === Response::HTTP_NOT_FOUND || $e->getCode() === Response::HTTP_FORBIDDEN) {
                return null;
            }

            throw $e;
        }

        $release = new Version();
        $release->title = $github['title'];
        $release->body = $github['body'];
        $release->date = new \DateTimeImmutable($github['date']);
        $release->version = $github['version'];
        $release->fixedVulnerabilities = $github['fixedVulnerabilities'];

        return $release;
    }

    /**
     * @param non-empty-array<string> $versions
     */
    private function resolveLatestVersion(array $versions): string
    {
        usort($versions, static function ($a, $b) {
            return version_compare($b, $a);
        });

        // Index them by major version
        $mappedVersions = [];

        foreach ($versions as $version) {
            if (str_contains($version, 'rc')) {
                continue;
            }

            $major = substr($version, 0, 3);

            if (isset($mappedVersions[$major])) {
                continue;
            }

            $mappedVersions[$major] = $version;
        }

        $currentMajor = substr($this->shopwareVersion, 0, 3);
        if (!isset($mappedVersions[$currentMajor])) {
            return strtolower($this->shopwareVersion);
        }

        $latestVersion = $mappedVersions[$currentMajor];

        $first = (int) substr($this->shopwareVersion, 0, 1);
        $second = (int) substr($this->shopwareVersion, 2, 1);
        ++$second;

        if (isset($mappedVersions[$first . '.' . $second])) {
            $latestVersion = $mappedVersions[$first . '.' . $second];
        }

        return $latestVersion;
    }
}
