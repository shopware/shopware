<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\SimpleS3\SimpleS3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\DecoratedAsyncS3Adapter;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory
 */
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

        $client = new SimpleS3Client([
            'region' => 'local',
            'endpoint' => 'http://localhost:9000',
            'pathStyleEndpoint' => true,
            'accessKeyId' => 'foo',
            'accessKeySecret' => 'bar',
        ]);

        static::assertEquals(
            new DecoratedAsyncS3Adapter(
                new AsyncAwsS3Adapter($client, 'private', 'foobar', new PortableVisibilityConverter()),
                'private',
                $client,
                'foobar'
            ),
            (new AwsS3v3Factory())->create($config)
        );
    }
}
