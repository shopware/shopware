<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AsyncAwsS3WriteBatchAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CopyBatch::class)]
class CopyBatchTest extends TestCase
{
    private const SOURCE_FILE = __DIR__ . '/_fixtures/test.txt';

    public function testCopy(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());

        $sourceFile = fopen(self::SOURCE_FILE, 'r');
        static::assertIsResource($sourceFile);
        CopyBatch::copy($fs, new CopyBatchInput(self::SOURCE_FILE, ['test.txt']), new CopyBatchInput($sourceFile, ['test2.txt']));

        static::assertTrue($fs->fileExists('test.txt'));
        static::assertTrue($fs->fileExists('test2.txt'));
        static::assertSame('test', $fs->read('test.txt'));
        static::assertSame('test', $fs->read('test2.txt'));
    }

    public function testCopyWithBatchCopyInterface(): void
    {
        $adapter = $this->createMock(AsyncAwsS3WriteBatchAdapter::class);
        $adapter->expects($this->once())->method('writeBatch');

        static::assertInstanceOf(WriteBatchInterface::class, $adapter);

        $fs = new Filesystem($adapter);

        $sourceFile = fopen(self::SOURCE_FILE, 'r');
        static::assertIsResource($sourceFile);
        CopyBatch::copy($fs, new CopyBatchInput(self::SOURCE_FILE, ['test.txt']), new CopyBatchInput($sourceFile, ['test2.txt']));
    }

    public function testConstructorThrowsAnExceptionWithNoResource(): void
    {
        static::expectException(AdapterException::class);
        /** @phpstan-ignore argument.type (for test purpose) */
        new CopyBatchInput(null, []);
    }
}
