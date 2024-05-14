<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Plugin;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;

/**
 * @internal
 */
#[CoversClass(CopyBatch::class)]
class CopyBatchTest extends TestCase
{
    public function testCopy(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());

        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $sourceFile = fopen($tmpFile, 'r');
        static::assertIsResource($sourceFile);
        CopyBatch::copy($fs, new CopyBatchInput($tmpFile, ['test.txt']), new CopyBatchInput($sourceFile, ['test2.txt']));

        static::assertTrue($fs->fileExists('test.txt'));
        static::assertTrue($fs->fileExists('test2.txt'));
        static::assertSame('test', $fs->read('test.txt'));
        static::assertSame('test', $fs->read('test2.txt'));

        unlink($tmpFile);
    }

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

        $fs = new Filesystem(new AsyncAwsS3Adapter($s3Client, 'test'));

        CopyBatch::copy($fs, new CopyBatchInput($sourceFile, ['test.txt']));
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

        $fs = new Filesystem(new AsyncAwsS3Adapter($s3Client, 'test'));

        CopyBatch::copy($fs, new CopyBatchInput($tmpFile, ['test.txt']));
    }

    public function testS3InvalidFile(): void
    {
        $s3Client = $this->createMock(S3Client::class);

        $s3Client
            ->expects(static::never())
            ->method('putObject');

        $fs = new Filesystem(new AsyncAwsS3Adapter($s3Client, 'test'));

        CopyBatch::copy($fs, new CopyBatchInput('invalid', ['test.txt']));
    }

    public function testConstructor(): void
    {
        static::expectException(\InvalidArgumentException::class);
        // @phpstan-ignore-next-line
        new CopyBatchInput(null, []);
    }
}
