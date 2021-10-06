<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Util;

use Google\Cloud\Core\Exception\NotFoundException;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\Plugin\Util\_fixture\Plugin\TestPlugin;
use Symfony\Component\HttpKernel\KernelInterface;

class AssetServiceTest extends TestCase
{
    public function testHandlesGoogleCloudNotFoundExceptionOnCopyAssetsFromBundle(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $kernel = $this->createMock(KernelInterface::class);
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $testPlugin = new TestPlugin(true, dirname(__FILE__) . '/_fixture');

        $kernel->method('getBundle')->willReturn($testPlugin);

        // The Google Cloud adapter of Flysystem will throw an error when a file does not exist.
        $filesystem->method('deleteDir')
            ->willThrowException(new NotFoundException('Google Cloud Bucket directory not found.'));

        $this->expectNotToPerformAssertions();

        (new AssetService(
            $filesystem,
            $kernel,
            new KernelPluginCollection([
                'test' => $testPlugin
            ]),
            $cacheInvalidator,
            '.'
        ))->copyAssetsFromBundle('test');
    }
}
