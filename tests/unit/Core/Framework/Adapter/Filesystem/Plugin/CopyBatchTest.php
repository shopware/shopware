<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AsyncAwsS3WriteBatchAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInterface;

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

    public function testCopyWithBatchCopyInterface(): void
    {
        $adapter = $this->createMock(AsyncAwsS3WriteBatchAdapter::class);
        $adapter->expects(static::once())->method('writeBatch');

        static::assertInstanceOf(WriteBatchInterface::class, $adapter);

        $fs = new Filesystem($adapter);

        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $sourceFile = fopen($tmpFile, 'r');
        static::assertIsResource($sourceFile);
        CopyBatch::copy($fs, new CopyBatchInput($tmpFile, ['test.txt']), new CopyBatchInput($sourceFile, ['test2.txt']));

        unlink($tmpFile);
    }

    public function testConstructor(): void
    {
        static::expectException(\InvalidArgumentException::class);
        // @phpstan-ignore-next-line
        new CopyBatchInput(null, []);
    }
}
