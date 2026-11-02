<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Asset;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Asset\Packages;

/**
 * @internal
 */
#[Package('framework')]
class BundleAssetVersionTest extends TestCase
{
    use KernelTestBehaviour;

    private const BUNDLE_DIRECTORY = 'bundles/framework';

    private FilesystemOperator $assetFilesystem;

    private Packages $packages;

    protected function setUp(): void
    {
        $this->assetFilesystem = static::getContainer()->get('shopware.filesystem.asset');
        $this->packages = static::getContainer()->get('assets.packages');
    }

    protected function tearDown(): void
    {
        $this->assetFilesystem->deleteDirectory(self::BUNDLE_DIRECTORY);
    }

    public function testBundleAssetIsVersionedByTheAssetFilesystem(): void
    {
        $path = self::BUNDLE_DIRECTORY . '/version-test-' . uniqid() . '.js';
        $this->assetFilesystem->write($path, 'console.log("asset filesystem");');

        static::assertFalse(
            static::getContainer()->get('shopware.filesystem.public')->fileExists($path),
            'The test needs the public and the asset filesystem to be separate'
        );

        $url = $this->packages->getUrl(basename($path), '@Framework');

        static::assertStringEndsWith(
            $path . '?' . $this->assetFilesystem->lastModified($path),
            $url
        );
    }

    public function testAssetInstallRefreshesTheBundleAssetVersion(): void
    {
        $file = 'assets/default/cms/preview_camera_large.jpg';
        $path = self::BUNDLE_DIRECTORY . '/' . $file;
        $this->assetFilesystem->deleteDirectory(self::BUNDLE_DIRECTORY);

        static::assertStringEndsWith($path, $this->packages->getUrl($file, '@Framework'));
        // Ends the storefront request, which persists the deferred cache entry without a version
        static::getContainer()->get('cache.object')->commit();

        static::getContainer()->get(AssetService::class)->copyAssetsFromBundle('Framework', true);

        static::assertStringEndsWith(
            $path . '?' . $this->assetFilesystem->lastModified($path),
            $this->packages->getUrl($file, '@Framework')
        );
    }
}
