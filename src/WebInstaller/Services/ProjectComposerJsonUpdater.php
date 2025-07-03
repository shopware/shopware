<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\WebInstaller\InstallerException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class ProjectComposerJsonUpdater
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function update(string $file, string $latestVersion): void
    {
        throw InstallerException::shouldNotLaunch($file, $latestVersion, $this->httpClient);
    }
}
