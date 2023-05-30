<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Processing\Reader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('system-settings')]
class CsvReaderTest extends TestCase
{
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    public function testSimpleCsv(): void
    {
        $content = implode(\PHP_EOL, [
            'foo;bar',
            '1;2',
            '"asdf";"zxcv"',
        ]);

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');
        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(2, $result);
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[0]);
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $result[1]);
    }

    public function testIncremental(): void
    {
        $content = 'foo;bar' . \PHP_EOL;
        $content .= '1;2' . \PHP_EOL;
        $content .= '"asdf";"zxcv"' . \PHP_EOL;

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, 0));
        static::assertSame(['foo' => '1', 'bar' => '2'], $record);

        $offset = $reader->getOffset();

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, $offset));
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $record);

        $offset = $reader->getOffset();
        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, $offset));
        static::assertNull($record);
    }

    public function testHeader(): void
    {
        $content = implode(\PHP_EOL, [
            'foo;bar',
            '1;2',
            '"asdf";"zxcv"',
            '"asdf";"zxcv";should not be included',
            '"remaining should be empty"',
            '"asdf";',
            ';zxcv',
            ';',
            '',
            ';;should be skipped',
        ]);

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');

        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(6, $result);

        $i = 0;
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[$i++]);
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $result[$i++]);
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $result[$i++]);
        static::assertSame(['foo' => 'remaining should be empty', 'bar' => ''], $result[$i++]);
        static::assertSame(['foo' => 'asdf', 'bar' => ''], $result[$i++]);
        static::assertSame(['foo' => '', 'bar' => 'zxcv'], $result[$i]);
    }

    public static function eolProvider(): array
    {
        return [
            ["\r\n"], // windows
            ["\n"], // unix
            //['\r'] // does not work :(
        ];
    }

    /**
     * @dataProvider eolProvider
     */
    public function testDifferentEOL($eol): void
    {
        $content = 'foo;bar' . $eol;
        $content .= '1;2' . $eol;
        $content .= '"asdf";"zxcv"' . $eol;

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');
        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(2, $result);
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[0]);
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $result[1]);
    }

    public function testUtf8BOMIsRemoved(): void
    {
        $content = 'foo;bar' . \PHP_EOL;
        $content .= '1;2' . \PHP_EOL;
        $content .= '"asdf";"zxcv"' . \PHP_EOL;

        $bomContent = self::BOM_UTF8 . $content;

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $bomContent, 'rb');
        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(2, $result);
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[0]);
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $result[1]);
    }

    public function testUf8BomOnlyRemovedAtBeginning(): void
    {
        $content = 'foo;bar' . \PHP_EOL;
        $content .= '1;2' . \PHP_EOL;
        $content .= self::BOM_UTF8 . 'asdf;"zxcv"' . \PHP_EOL;

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'rb');
        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(2, $result);
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[0]);
        static::assertSame(['foo' => self::BOM_UTF8 . 'asdf', 'bar' => 'zxcv'], $result[1]);
    }

    private function getAll(iterable $iterable): array
    {
        $result = [];

        foreach ($iterable as $key => $record) {
            $result[$key] = $record;
        }

        return $result;
    }

    private function getFirst(iterable $iterable)
    {
        foreach ($iterable as $first) {
            return $first;
        }
    }
}
