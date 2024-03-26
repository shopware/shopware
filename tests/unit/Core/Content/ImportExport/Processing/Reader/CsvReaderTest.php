<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CsvReader::class)]
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
        $resource = fopen('data://text/plain,' . $content, 'r');
        static::assertIsResource($resource);
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
        $resource = fopen('data://text/plain,' . $content, 'r');
        static::assertIsResource($resource);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, 0));
        static::assertSame(['foo' => '1', 'bar' => '2'], $record);

        $offset = $reader->getOffset();

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'r');
        static::assertIsResource($resource);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, $offset));
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $record);

        $offset = $reader->getOffset();
        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'r');
        static::assertIsResource($resource);
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
        $resource = fopen('data://text/plain,' . $content, 'r');

        static::assertIsResource($resource);
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

    public static function eolProvider(): \Generator
    {
        yield 'windows' => ["\r\n"];
        yield 'unix' => ["\n"];
    }

    #[DataProvider('eolProvider')]
    public function testDifferentEOL(string $eol): void
    {
        $content = 'foo;bar' . $eol;
        $content .= '1;2' . $eol;
        $content .= '"asdf";"zxcv"' . $eol;

        $reader = new CsvReader();
        $resource = fopen('data://text/plain,' . $content, 'r');
        static::assertIsResource($resource);
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
        $resource = fopen('data://text/plain,' . $bomContent, 'r');
        static::assertIsResource($resource);
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
        $resource = fopen('data://text/plain,' . $content, 'r');
        static::assertIsResource($resource);
        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(2, $result);
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[0]);
        static::assertSame(['foo' => self::BOM_UTF8 . 'asdf', 'bar' => 'zxcv'], $result[1]);
    }

    /**
     * @param iterable<array<string>> $iterable
     *
     * @return array<array<string>>
     */
    private function getAll(iterable $iterable): array
    {
        $result = [];

        foreach ($iterable as $key => $record) {
            $result[$key] = $record;
        }

        return $result;
    }

    /**
     * @param iterable<array<string>> $iterable
     *
     * @return array<string>|null
     */
    private function getFirst(iterable $iterable): ?array
    {
        foreach ($iterable as $first) {
            return $first;
        }

        return null;
    }
}
