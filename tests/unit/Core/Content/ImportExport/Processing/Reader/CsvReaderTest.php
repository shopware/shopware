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
#[Package('fundamentals@after-sales')]
#[CoversClass(CsvReader::class)]
class CsvReaderTest extends TestCase
{
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    /**
     * @var list<array{process: resource, stdout: resource, stderr: resource}>
     */
    private array $openProcesses = [];

    protected function tearDown(): void
    {
        foreach ($this->openProcesses as ['process' => $process, 'stdout' => $stdout, 'stderr' => $stderr]) {
            if (\is_resource($stdout)) {
                fclose($stdout);
            }

            if (\is_resource($stderr)) {
                stream_get_contents($stderr);
                fclose($stderr);
            }

            if (\is_resource($process)) {
                proc_close($process);
            }
        }

        $this->openProcesses = [];

        parent::tearDown();
    }

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

    public function testHeaderOnlyFileAdvancesOffsetToEof(): void
    {
        $content = 'url http://127.0.0.1:8000/media/%C3%9Fhopware-log%C3%B6.png';

        $reader = new CsvReader();
        $resource = fopen('php://temp', 'r+');
        static::assertIsResource($resource);
        fwrite($resource, $content);
        rewind($resource);

        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertSame([], $result);
        static::assertSame(\strlen($content), $reader->getOffset());
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

    public function testReadsNonSeekableStream(): void
    {
        $content = self::BOM_UTF8 . 'foo;bar' . \PHP_EOL;
        $content .= '1;2' . \PHP_EOL;
        $content .= '"asdf";"zxcv"' . \PHP_EOL;

        $reader = new CsvReader();
        $resource = $this->openNonSeekableStream($content);
        $result = $this->getAll($reader->read(new Config([], [], []), $resource, 0));

        static::assertCount(2, $result);
        static::assertSame(['foo' => '1', 'bar' => '2'], $result[0]);
        static::assertSame(['foo' => 'asdf', 'bar' => 'zxcv'], $result[1]);
    }

    public function testIncrementalReadOnNonSeekableStream(): void
    {
        $content = 'foo;bar' . \PHP_EOL;
        $content .= '"value one foo";"value one bar"' . \PHP_EOL;
        $content .= '"value two foo";"value two bar"' . \PHP_EOL;
        $content .= '100;200' . \PHP_EOL;

        $reader = new CsvReader();
        $resource = $this->openNonSeekableStream($content);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, 0));
        static::assertSame(['foo' => 'value one foo', 'bar' => 'value one bar'], $record);

        $offset = $reader->getOffset();

        $reader = new CsvReader();
        $resource = $this->openNonSeekableStream($content);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, $offset));
        static::assertSame(['foo' => 'value two foo', 'bar' => 'value two bar'], $record);

        $offset = $reader->getOffset();

        $reader = new CsvReader();
        $resource = $this->openNonSeekableStream($content);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, $offset));
        static::assertSame(['foo' => '100', 'bar' => '200'], $record);
    }

    public function testIncrementalReadOnNonSeekableStreamWithOffsetLargerThanSeekChunkSize(): void
    {
        $largeValue = str_repeat('thisIsATest', 1000);
        $content = 'foo;bar' . \PHP_EOL;
        $content .= $largeValue . ';value one bar' . \PHP_EOL;
        $content .= 'value two foo;value two bar' . \PHP_EOL;

        $reader = new CsvReader();
        $resource = $this->openNonSeekableStream($content);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, 0));

        static::assertSame(['foo' => $largeValue, 'bar' => 'value one bar'], $record);

        $offset = $reader->getOffset();
        static::assertGreaterThan(1024, $offset);

        $reader = new CsvReader();
        $resource = $this->openNonSeekableStream($content);
        $record = $this->getFirst($reader->read(new Config([], [], []), $resource, $offset));

        static::assertSame(['foo' => 'value two foo', 'bar' => 'value two bar'], $record);
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

    /**
     * @return resource
     */
    private function openNonSeekableStream(string $content)
    {
        $command = \sprintf(
            'fwrite(STDOUT, base64_decode(%s));',
            var_export(base64_encode($content), true)
        );
        $process = proc_open(
            [\PHP_BINARY, '-r', $command],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        static::assertIsResource($process);
        static::assertIsArray($pipes);

        $resource = $pipes[1] ?? null;
        static::assertIsResource($resource);
        static::assertArrayHasKey(2, $pipes);
        static::assertIsResource($pipes[2]);
        static::assertFalse(stream_get_meta_data($resource)['seekable']);

        $this->openProcesses[] = [
            'process' => $process,
            'stdout' => $resource,
            'stderr' => $pipes[2],
        ];

        return $resource;
    }
}
