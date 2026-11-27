<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Writer\CsvFileWriter;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CsvFileWriter::class)]
class CsvFileWriterTest extends TestCase
{
    public function testValuesAreEscapedButTheHeaderRowIsNot(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $config = new Config([], [], []);

        $writer = new CsvFileWriter($filesystem);
        $writer->append($config, ['=firstName' => '=1+1', 'city' => 'Berlin'], 0);
        $writer->append($config, ['=firstName' => '@SUM(1)', 'city' => 'Hamburg'], 1);
        $writer->finish($config, 'export.csv');

        static::assertSame("=firstName;city\n'=1+1;Berlin\n'@SUM(1);Hamburg\n", $filesystem->read('export.csv'));
    }

    public function testABackslashBeforeAQuoteKeepsTheValueInOneCellForSpreadsheets(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $config = new Config([], [], []);

        $writer = new CsvFileWriter($filesystem);
        $writer->append($config, ['name' => 'Max', 'street' => 'A\";=1+1;\"', 'city' => 'Berlin'], 0);
        $writer->finish($config, 'export.csv');

        $resource = $filesystem->readStream('export.csv');
        fgetcsv($resource, 0, ';', '"', '');

        static::assertSame(['Max', 'A\";=1+1;\"', 'Berlin'], fgetcsv($resource, 0, ';', '"', ''));
    }
}
