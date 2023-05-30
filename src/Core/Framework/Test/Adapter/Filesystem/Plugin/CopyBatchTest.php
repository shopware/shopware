<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem\Plugin;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;

/**
 * @internal
 */
class CopyBatchTest extends TestCase
{
    public function testCopy(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());

        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $sourceFile = fopen($tmpFile, 'rb');
        static::assertIsResource($sourceFile);
        CopyBatch::copy($fs, new CopyBatchInput($tmpFile, ['test.txt']), new CopyBatchInput($sourceFile, ['test2.txt']));

        static::assertTrue($fs->fileExists('test.txt'));
        static::assertTrue($fs->fileExists('test2.txt'));
        static::assertSame('test', $fs->read('test.txt'));
        static::assertSame('test', $fs->read('test2.txt'));

        unlink($tmpFile);
    }

    public function testConstructor(): void
    {
        static::expectException(\InvalidArgumentException::class);
        //@phpstan-ignore-next-line
        new CopyBatchInput(null, []);
    }
}
