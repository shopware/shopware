<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;

/**
 * @internal
 */
#[CoversClass(AwsS3v3Factory::class)]
class AwsS3v3FactoryTest extends TestCase
{
    public function testGetType(): void
    {
        static::assertEquals('amazon-s3', (new AwsS3v3Factory())->getType());
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
            new AsyncAwsS3Adapter($client, 'private', 'foobar', new PortableVisibilityConverter()),
            (new AwsS3v3Factory())->create($config)
        );
    }
}
