<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\GoogleStorageFactory;

/**
 * @internal
 */
#[CoversClass(GoogleStorageFactory::class)]
class GoogleStorageFactoryTest extends TestCase
{
    public function testCreateGoogleStorageFromConfigString(): void
    {
        $googleStorageFactory = new GoogleStorageFactory();
        static::assertSame('google-storage', $googleStorageFactory->getType());

        /** @var string $key */
        $key = file_get_contents(__DIR__ . '/fixtures/keyfile.json');

        $config = [
            'projectId' => 'TestGoogleStorage',
            'keyFile' => json_decode($key, true, 512, \JSON_THROW_ON_ERROR),
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        static::assertInstanceOf(GoogleCloudStorageAdapter::class, $googleStorageFactory->create($config));
    }

    public function testCreateGoogleStorageFromConfigFile(): void
    {
        $googleStorageFactory = new GoogleStorageFactory();

        $config = [
            'projectId' => 'TestGoogleStorage',
            'keyFilePath' => __DIR__ . '/fixtures/keyfile.json',
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        static::assertInstanceOf(GoogleCloudStorageAdapter::class, $googleStorageFactory->create($config));
    }

    public function testCreateGoogleStorageWithoutExplicitCredentials(): void
    {
        $googleStorageFactory = new GoogleStorageFactory();

        $config = [
            'projectId' => 'TestGoogleStorage',
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        static::assertInstanceOf(GoogleCloudStorageAdapter::class, $googleStorageFactory->create($config));
    }
}
