<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Asset;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

class FlysystemLastModifiedVersionStrategyTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var Package
     */
    private $asset;

    public function setUp(): void
    {
        $this->fs = new Filesystem(new Local(sys_get_temp_dir() . '/' . uniqid(self::class, true)));
        $this->asset = new UrlPackage(['http://shopware.com'], new FlysystemLastModifiedVersionStrategy('test', $this->fs, new FilesystemTagAwareAdapter('test', 0, sys_get_temp_dir() . '/cache-' . uniqid(self::class, true))));
    }

    public function testNonExistentFile(): void
    {
        $url = $this->asset->getUrl('test');
        static::assertSame('http://shopware.com/test', $url);
    }

    public function testExistsFile(): void
    {
        $this->fs->write('testFile', 'yea');
        $metaData = $this->fs->getMetadata('testFile');
        $url = $this->asset->getUrl('testFile');
        static::assertSame('http://shopware.com/testFile?' . $metaData['timestamp'] . ($metaData['size'] ?? '0'), $url);
    }

    public function testFolder(): void
    {
        $this->fs->write('folder/file', 'test');

        $metaData = $this->fs->getMetadata('folder');
        $url = $this->asset->getUrl('folder');
        static::assertSame('http://shopware.com/folder?' . $metaData['timestamp'] . '0', $url);
    }
}
