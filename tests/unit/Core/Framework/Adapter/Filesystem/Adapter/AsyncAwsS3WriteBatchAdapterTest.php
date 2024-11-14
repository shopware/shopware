<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AsyncAwsS3WriteBatchAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;

/**
 * @internal
 */
#[CoversClass(AsyncAwsS3WriteBatchAdapter::class)]
class AsyncAwsS3WriteBatchAdapterTest extends TestCase
{
    public function testS3(): void
    {
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $sourceFile = fopen($tmpFile, 'rb');
        static::assertIsResource($sourceFile);

        $s3Client = $this->createMock(S3Client::class);

        $result = ResultMockFactory::create(PutObjectOutput::class);
        $s3Client
            ->method('putObject')
            ->with([
                'Bucket' => 'test',
                'Key' => 'test.txt',
                'Body' => $sourceFile,
                'ContentType' => 'text/plain',
            ])
            ->willReturn($result);

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test');
        $adapter->writeBatch(new CopyBatchInput($sourceFile, ['test.txt']));
    }

    public function testS3UsingPath(): void
    {
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $s3Client = $this->createMock(S3Client::class);

        $result = ResultMockFactory::create(PutObjectOutput::class);
        $s3Client
            ->method('putObject')
            ->willReturnCallback(function (array $input) use ($result) {
                static::assertSame('test', $input['Bucket']);
                static::assertSame('test.txt', $input['Key']);
                static::assertSame('text/plain', $input['ContentType']);

                return $result;
            });

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test');

        $adapter->writeBatch(new CopyBatchInput($tmpFile, ['test.txt']));
    }

    public function testS3InvalidFile(): void
    {
        $s3Client = $this->createMock(S3Client::class);

        $s3Client
            ->expects(static::never())
            ->method('putObject');

        $adapter = new AsyncAwsS3WriteBatchAdapter($s3Client, 'test');
        $adapter->writeBatch(new CopyBatchInput('invalid', ['test.txt']));
    }
}
