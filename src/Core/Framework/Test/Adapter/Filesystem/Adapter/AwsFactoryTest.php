<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem\Adapter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\DecoratedAsyncS3Adapter;

/**
 * @internal
 */
class AwsFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $factory = new AwsS3v3Factory();
        static::assertSame('amazon-s3', $factory->getType());

        $adapter = $factory->create([
            'bucket' => 'test',
            'region' => 'test',
            'endpoint' => 'http://localhost',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'test',
                'secret' => 'test',
            ],
        ]);

        static::assertInstanceOf(DecoratedAsyncS3Adapter::class, $adapter);
    }
}
