<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
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
    private Filesystem $filesystem;

    private CsvFileWriter $writer;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->writer = new CsvFileWriter($this->filesystem);
    }

    public function testFormulaValuesArePrefixedAndHeaderAndSafeValuesAreUntouched(): void
    {
        $config = new Config([], [], []);

        $this->writer->append($config, [
            'equals' => '=1+1',
            'plus' => '+2',
            'minus' => '-3',
            'at' => '@SUM(1)',
            'tab' => "\tTabbed",
            'carriage_return' => "\rReturn",
            'inner' => 'a=b=c',
            'street' => 'Main Street 1',
        ], 0);
        $this->writer->finish($config, 'export.csv');

        $rows = $this->readCsv('export.csv');

        // header keys are used to map columns back on import, so they stay intact
        static::assertSame(
            ['equals', 'plus', 'minus', 'at', 'tab', 'carriage_return', 'inner', 'street'],
            $rows[0]
        );

        static::assertSame('\'=1+1', $rows[1][0]);
        static::assertSame('\'+2', $rows[1][1]);
        static::assertSame('\'-3', $rows[1][2]);
        static::assertSame('\'@SUM(1)', $rows[1][3]);
        static::assertSame("'\tTabbed", $rows[1][4]);
        static::assertSame("'\rReturn", $rows[1][5]);
        // a trigger character that is not the first character must not be escaped
        static::assertSame('a=b=c', $rows[1][6]);
        static::assertSame('Main Street 1', $rows[1][7]);
    }

    public function testReimportingTheRawExportedFileKeepsTheEscapingQuote(): void
    {
        $config = new Config([], [], []);

        // a value that legitimately starts with a trigger character, e.g. a phone number
        $this->writer->append($config, ['phone' => '+49123', 'city' => 'Berlin'], 0);
        $this->writer->finish($config, 'export.csv');

        $reader = new CsvReader();
        $stream = fopen('php://temp', 'r+');
        static::assertIsResource($stream);
        fwrite($stream, $this->filesystem->read('export.csv'));
        rewind($stream);

        /** @var list<array<string, string>> $rows */
        $rows = iterator_to_array($reader->read($config, $stream, 0), false);
        fclose($stream);

        // Documented behaviour: importing the raw exported file back keeps the escaping quote,
        // so a formula-prefixed value changes by one leading quote. Opening and saving the file
        // in a spreadsheet application removes the quote, so a round-trip through a spreadsheet
        // stays lossless. We escape on export only and never strip on import, so the neutralization
        // is unconditional.
        static::assertCount(1, $rows);
        static::assertSame('\'+49123', $rows[0]['phone']);
        static::assertSame('Berlin', $rows[0]['city']);
    }

    #[DataProvider('triggerProvider')]
    public function testEachTriggerCharacterIsEscaped(string $value, string $expected): void
    {
        $config = new Config([], [], []);

        // a trailing column keeps an empty first field from being read back as null by str_getcsv
        $this->writer->append($config, ['value' => $value, 'end' => 'x'], 0);
        $this->writer->finish($config, 'export.csv');

        $rows = $this->readCsv('export.csv');
        static::assertSame($expected, $rows[1][0]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function triggerProvider(): iterable
    {
        yield 'equals' => ['=cmd', '\'=cmd'];
        yield 'plus' => ['+1', '\'+1'];
        yield 'minus' => ['-1', '\'-1'];
        yield 'at' => ['@foo', '\'@foo'];
        yield 'tab' => ["\tfoo", "'\tfoo"];
        yield 'carriage return' => ["\rfoo", "'\rfoo"];
        yield 'line feed' => ["\nfoo", "'\nfoo"];
        yield 'full-width equals' => ['＝1+1', '\'＝1+1'];
        yield 'full-width plus' => ['＋1', '\'＋1'];
        yield 'full-width minus' => ['－1', '\'－1'];
        yield 'full-width at' => ['＠foo', '\'＠foo'];
        yield 'safe text' => ['foo', 'foo'];
        yield 'safe number' => ['42', '42'];
        yield 'empty' => ['', ''];
    }

    /**
     * Reads the written file with a real CSV parser so fields that contain quoted
     * newlines or other control characters are returned as single cells.
     *
     * @return list<list<string|null>>
     */
    private function readCsv(string $path): array
    {
        $stream = fopen('php://temp', 'r+');
        static::assertIsResource($stream);
        fwrite($stream, $this->filesystem->read($path));
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ';', '"', '\\')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
