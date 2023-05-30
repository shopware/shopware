<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem\Adapter;

use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\GoogleStorageFactory;

/**
 * @internal
 */
class GoogleStorageFactoryTest extends TestCase
{
    public function testCreateGoogleStorageFromConfigString(): void
    {
        $googleStorageFactory = new GoogleStorageFactory();
        static::assertSame('google-storage', $googleStorageFactory->getType());

        $config = [
            'projectId' => 'TestGoogleStorage',
            'keyFile' => json_decode(file_get_contents(__DIR__ . '/fixtures/keyfile.json'), true, 512, \JSON_THROW_ON_ERROR),
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        try {
            static::assertInstanceOf(GoogleCloudStorageAdapter::class, $googleStorageFactory->create($config));
        } catch (\Exception $e) {
            static::fail($e->getMessage());
        }
    }

    public function testCreateGoogleStorageFromConfigFile(): void
    {
        /** @var GoogleStorageFactory $googleStorageFactory */
        $googleStorageFactory = new GoogleStorageFactory();

        $config = [
            'projectId' => 'TestGoogleStorage',
            'keyFilePath' => __DIR__ . '/fixtures/keyfile.json',
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        try {
            static::assertInstanceOf(GoogleCloudStorageAdapter::class, $googleStorageFactory->create($config));
        } catch (\Exception $e) {
            static::fail($e->getMessage());
        }
    }
}
