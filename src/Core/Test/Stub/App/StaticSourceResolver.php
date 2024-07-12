<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\App;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[Package('core')]
class StaticSourceResolver extends SourceResolver
{
    /**
     * Map of app names to filesystems
     *
     * @param array<string, Filesystem> $filesystems
     */
    public function __construct(private array $filesystems = [])
    {
    }

    public function addFs(string $appName, Filesystem $filesystem): void
    {
        $this->filesystems[$appName] = $filesystem;
    }

    public function resolveSourceType(Manifest $manifest): string
    {
        return 'static';
    }

    public function filesystemForManifest(Manifest $manifest): Filesystem
    {
        if (!isset($this->filesystems[$manifest->getMetadata()->getName()])) {
            return new StaticFilesystem();
        }

        return $this->filesystems[$manifest->getMetadata()->getName()];
    }

    public function filesystemForApp(AppEntity $app): Filesystem
    {
        if (!isset($this->filesystems[$app->getName()])) {
            return new StaticFilesystem();
        }

        return $this->filesystems[$app->getName()];
    }

    public function filesystemForAppName(string $appName): Filesystem
    {
        if (!isset($this->filesystems[$appName])) {
            return new StaticFilesystem();
        }

        return $this->filesystems[$appName];
    }
}
