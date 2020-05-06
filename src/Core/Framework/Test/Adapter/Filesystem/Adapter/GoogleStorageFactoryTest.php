<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem\Adapter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\GoogleStorageFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class GoogleStorageFactoryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @doesNotPerformAssertions
     */
    public function testCreateGoogleStorageFromConfigString(): void
    {
        /** @var GoogleStorageFactory $googleStorageFactory */
        $googleStorageFactory = $this->getContainer()->get('Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory.google_storage');

        $config = [
            'projectId' => 'TestGoogleStorage',
            'keyFile' => json_decode(file_get_contents(__DIR__ . '/fixtures/keyfile.json'), true),
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        try {
            $googleStorageFactory->create($config);
        } catch (\Exception $e) {
            static::fail($e->getMessage());
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCreateGoogleStorageFromConfigFile(): void
    {
        /** @var GoogleStorageFactory $googleStorageFactory */
        $googleStorageFactory = $this->getContainer()->get('Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory.google_storage');

        $config = [
            'projectId' => 'TestGoogleStorage',
            'keyFilePath' => __DIR__ . '/fixtures/keyfile.json',
            'bucket' => 'TestBucket',
            'root' => '/',
        ];

        try {
            $googleStorageFactory->create($config);
        } catch (\Exception $e) {
            static::fail($e->getMessage());
        }
    }
}
