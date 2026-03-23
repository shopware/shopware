<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\Core\AbstractApi;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AsyncAwsS3WriteBatchAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[CoversClass(AwsS3v3Factory::class)]
class AwsS3v3FactoryTest extends TestCase
{
    public function testGetType(): void
    {
        static::assertSame('amazon-s3', (new AwsS3v3Factory())->getType());
    }

    public function testCreate(): void
    {
        $config = [
            'bucket' => 'private',
            'endpoint' => 'http://localhost:9000',
            'use_path_style_endpoint' => true,
            'region' => 'local',
            'root' => 'foobar',
            'credentials' => [
                'key' => 'foo',
                'secret' => 'bar',
            ],
            'options' => [
                'visibility' => 'private',
            ],
        ];

        $client = new S3Client([
            'region' => 'local',
            'endpoint' => 'http://localhost:9000',
            'pathStyleEndpoint' => '1',
            'accessKeyId' => 'foo',
            'accessKeySecret' => 'bar',
        ]);

        static::assertEquals(
            new AsyncAwsS3WriteBatchAdapter($client, 'private', 'foobar', new PortableVisibilityConverter()),
            (new AwsS3v3Factory())->create($config)
        );
    }

    public function testCreateEmptyEndpointIsNotConsidered(): void
    {
        $config = [
            'bucket' => 'private',
            'endpoint' => '',
            'use_path_style_endpoint' => true,
            'region' => 'local',
            'root' => 'foobar',
            'credentials' => [
                'key' => 'foo',
                'secret' => 'bar',
            ],
            'options' => [
                'visibility' => 'private',
            ],
        ];

        $client = new S3Client([
            'region' => 'local',
            'pathStyleEndpoint' => '1',
            'accessKeyId' => 'foo',
            'accessKeySecret' => 'bar',
        ]);

        static::assertEquals(
            new AsyncAwsS3WriteBatchAdapter($client, 'private', 'foobar', new PortableVisibilityConverter()),
            (new AwsS3v3Factory())->create($config)
        );
    }

    public function testCreateWithCustomBatchSize(): void
    {
        $config = [
            'bucket' => 'private',
            'region' => 'local',
            'root' => 'foobar',
        ];

        $customBatchSize = 100;
        $factory = new AwsS3v3Factory($customBatchSize);

        $client = new S3Client([
            'region' => 'local',
        ]);

        $adapter = new AsyncAwsS3WriteBatchAdapter($client, 'private', 'foobar', new PortableVisibilityConverter());
        $adapter->batchSize = $customBatchSize;

        static::assertEquals(
            $adapter,
            $factory->create($config)
        );
    }

    public function testCreateWithCustomHttpClient(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $config = [
            'bucket' => 'private',
            'region' => 'local',
            'root' => 'foobar',
        ];

        $factory = new AwsS3v3Factory(250, $httpClient);
        $adapter = $factory->create($config);

        static::assertInstanceOf(AsyncAwsS3WriteBatchAdapter::class, $adapter);

        // Verify the custom HTTP client was forwarded to the underlying S3Client
        $s3Client = (new \ReflectionProperty(AsyncAwsS3Adapter::class, 'client'))->getValue($adapter);
        $actualHttpClient = (new \ReflectionProperty(AbstractApi::class, 'httpClient'))->getValue($s3Client);
        static::assertSame($httpClient, $actualHttpClient);
    }
}
