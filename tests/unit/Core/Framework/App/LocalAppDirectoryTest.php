<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\LocalAppDirectory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\ExtensionExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LocalAppDirectory::class)]
class LocalAppDirectoryTest extends TestCase
{
    public function testStoreDelegatesToExtensionExtractor(): void
    {
        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('/tmp/App.zip', false, PluginManagementService::APP);

        (new LocalAppDirectory('/app', new NullLogger(), $extractor))->store('/tmp/App.zip');
    }

    public function testLoadAndDelete(): void
    {
        $appDir = sys_get_temp_dir() . '/local-app-directory-' . bin2hex(random_bytes(5));
        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__ . '/_fixtures/App/TestApp', $appDir . '/TestApp');

        $directory = new LocalAppDirectory($appDir, new NullLogger(), static::createStub(ExtensionExtractor::class), $filesystem);

        try {
            $apps = $directory->load();

            static::assertArrayHasKey('minimal', $apps);
            static::assertInstanceOf(Manifest::class, $apps['minimal']);

            $directory->delete('minimal');

            static::assertDirectoryDoesNotExist($appDir . '/TestApp');
        } finally {
            $filesystem->remove($appDir);
        }
    }

    public function testDeleteUnknownAppDoesNothing(): void
    {
        $appDir = sys_get_temp_dir() . '/local-app-directory-' . bin2hex(random_bytes(5));
        $filesystem = new Filesystem();
        $filesystem->mkdir($appDir);

        try {
            (new LocalAppDirectory($appDir, new NullLogger(), static::createStub(ExtensionExtractor::class), $filesystem))->delete('UnknownApp');

            static::assertDirectoryExists($appDir);
        } finally {
            $filesystem->remove($appDir);
        }
    }
}
