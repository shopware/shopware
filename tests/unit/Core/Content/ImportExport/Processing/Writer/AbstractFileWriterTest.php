<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractFileWriter;
use Shopware\Core\Content\ImportExport\Processing\Writer\CsvFileWriter;
use Shopware\Core\Content\ImportExport\Struct\Config as ExportConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(AbstractFileWriter::class)]
class AbstractFileWriterTest extends TestCase
{
    public function testTempFileIsRemovedWhenWriterIsDestroyedWithoutFinish(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $config = new ExportConfig([], [], []);
        $id = Uuid::randomHex();
        $expected = 'id' . \PHP_EOL . $id . \PHP_EOL;
        $tempDir = sys_get_temp_dir();
        $filesBefore = scandir($tempDir) ?: [];

        $writer = new CsvFileWriter($filesystem);
        $writer->append($config, ['id' => $id], 0);
        $writer->flush($config, 'export.csv.offset_0');

        $tempFiles = array_values(array_filter(
            array_map(static fn (string $file) => $tempDir . '/' . $file, array_diff(scandir($tempDir) ?: [], $filesBefore)),
            static fn (string $path) => is_file($path) && file_get_contents($path) === $expected,
        ));
        static::assertCount(1, $tempFiles);

        unset($writer);

        static::assertFileDoesNotExist($tempFiles[0]);
        static::assertSame($expected, $filesystem->read('export.csv.offset_0'));
    }

    public function testTempFilesAreRemovedWhenFilesystemClosesTheStream(): void
    {
        $filesystem = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public function writeStream(string $path, $contents, Config $config): void
            {
                parent::writeStream($path, $contents, $config);

                fclose($contents);
            }
        });
        $config = new ExportConfig([], [], []);
        $tempDir = sys_get_temp_dir();
        $filesBefore = scandir($tempDir) ?: [];

        $writer = new CsvFileWriter($filesystem);
        $writer->append($config, ['id' => Uuid::randomHex()], 0);
        $writer->flush($config, 'export.csv.offset_0');
        $writer->append($config, ['id' => Uuid::randomHex()], 1);
        $writer->finish($config, 'export.csv.offset_1');

        static::assertSame([], array_values(array_diff(scandir($tempDir) ?: [], $filesBefore)));
    }
}
