<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\S3ClientFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(S3ClientFactory::class)]
class S3ClientFactoryTest extends TestCase
{
    public function testCreateWithMinimalConfig(): void
    {
        $result = S3ClientFactory::create([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
        ]);

        static::assertInstanceOf(S3Client::class, $result['client']);
        static::assertSame('test-bucket', $result['bucket']);
        static::assertSame('', $result['root']);
    }

    public function testCreateWithFullConfig(): void
    {
        $result = S3ClientFactory::create([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
            'root' => 'media/files',
            'endpoint' => 'http://localhost:9000',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'access-key',
                'secret' => 'secret-key',
            ],
        ]);

        static::assertInstanceOf(S3Client::class, $result['client']);
        static::assertSame('test-bucket', $result['bucket']);
        static::assertSame('media/files', $result['root']);
    }

    public function testCreateWithoutCredentialsUsesIAMRole(): void
    {
        $result = S3ClientFactory::create([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
        ]);

        static::assertInstanceOf(S3Client::class, $result['client']);
    }

    public function testCreateWithEmptyCredentialsUsesIAMRole(): void
    {
        $result = S3ClientFactory::create([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
            'credentials' => [],
        ]);

        static::assertInstanceOf(S3Client::class, $result['client']);
    }

    public function testCreateWithNullCredentialsUsesIAMRole(): void
    {
        $result = S3ClientFactory::create([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
            'credentials' => null,
        ]);

        static::assertInstanceOf(S3Client::class, $result['client']);
    }

    public function testCreateThrowsOnMissingBucket(): void
    {
        $this->expectException(MissingOptionsException::class);

        S3ClientFactory::create([
            'region' => 'eu-west-1',
        ]);
    }

    public function testCreateThrowsOnMissingRegion(): void
    {
        $this->expectException(MissingOptionsException::class);

        S3ClientFactory::create([
            'bucket' => 'test-bucket',
        ]);
    }

    public function testCreateThrowsOnIncompleteCredentials(): void
    {
        $this->expectException(MissingOptionsException::class);

        S3ClientFactory::create([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
            'credentials' => [
                'key' => 'access-key',
                // missing secret
            ],
        ]);
    }
}
