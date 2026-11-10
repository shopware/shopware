<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AsyncAwsS3WriteBatchAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AsyncAwsS3WriteBatchAdapter::class)]
class AsyncAwsS3WriteBatchAdapterTest extends TestCase
{
    private const SOURCE_FILE = __DIR__ . '/fixtures/test.txt';

    public function testS3(): void
    {
        $sourceFile = fopen(self::SOURCE_FILE, 'rb');
        static::assertIsResource($sourceFile);

        $s3Client = $this->createMock(S3Client::class);

        $result = ResultMockFactory::create(PutObjectOutput::class);
        $s3Client
            ->expects($this->once())
            ->method('putObject')
            ->willReturnCallback(static function (array $input) use ($sourceFile, $result) {
                static::assertSame([
                    'Bucket' => 'test',
                    'Key' => 'test.txt',
                    'Body' => $sourceFile,
                    'ACL' => 'public-read',
                    'ContentType' => 'text/plain',
                ], $input);

                return $result;
            });

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test', '', new PortableVisibilityConverter());
        $adapter->writeBatch(new CopyBatchInput($sourceFile, ['test.txt']));
    }

    public function testS3UsingPath(): void
    {
        $s3Client = static::createStub(S3Client::class);

        $result = ResultMockFactory::create(PutObjectOutput::class);
        $s3Client
            ->method('putObject')
            ->willReturnCallback(static function (array $input) use ($result) {
                static::assertSame('test', $input['Bucket']);
                static::assertSame('test.txt', $input['Key']);
                static::assertSame('text/plain', $input['ContentType']);
                static::assertSame('public-read', $input['ACL']);

                return $result;
            });

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test', '', new PortableVisibilityConverter());

        $adapter->writeBatch(new CopyBatchInput(self::SOURCE_FILE, ['test.txt']));
    }

    public function testS3InvalidFile(): void
    {
        $s3Client = $this->createMock(S3Client::class);

        $s3Client
            ->expects($this->never())
            ->method('putObject');

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test', '', new PortableVisibilityConverter());
        $adapter->writeBatch(new CopyBatchInput('invalid', ['test.txt']));
    }

    public function testConfigurableBatchSize(): void
    {
        $s3Client = $this->createMock(S3Client::class);
        $result = ResultMockFactory::create(PutObjectOutput::class);

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test', '', new PortableVisibilityConverter());
        $adapter->batchSize = 2;

        $files = [];
        for ($i = 0; $i < 5; ++$i) {
            $files[] = new CopyBatchInput(self::SOURCE_FILE, ["test{$i}.txt"]);
        }

        $s3Client
            ->expects($this->exactly(5))
            ->method('putObject')
            ->willReturn($result);

        $adapter->writeBatch(...$files);
    }
}
