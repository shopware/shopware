<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\ArrayWriter;

/**
 * @internal
 */
#[CoversClass(ArrayWriter::class)]
class ArrayWriterTest extends TestCase
{
    private string $fixtureFile;

    protected function setUp(): void
    {
        $this->fixtureFile = __DIR__ . '/ArrayWriterTestFixture.php';
        // No need to unlink here; only tearDown will clean up
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixtureFile)) {
            unlink($this->fixtureFile);
        }
    }

    public function testSetAndGet(): void
    {
        $writer = new ArrayWriter('/dev/null');
        $writer->set('foo', 'bar');
        static::assertSame('bar', $writer->get('foo'));
    }

    public function testGetThrowsIfKeyMissing(): void
    {
        $writer = new ArrayWriter('/dev/null');
        $this->expectException(\InvalidArgumentException::class);
        $writer->get('missing');
    }

    public function testEnsure(): void
    {
        $writer = new ArrayWriter('/dev/null');
        $writer->ensure('foo');
        static::assertSame('__EMPTY__', $writer->get('foo'));
        // Should not overwrite existing
        $writer->set('foo', 'bar');
        $writer->ensure('foo');
        static::assertSame('bar', $writer->get('foo'));
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('dumpProvider')]
    public function testDump(array $data, bool $long, string $expected): void
    {
        $writer = new ArrayWriter($this->fixtureFile);
        foreach ($data as $k => $v) {
            $writer->set($k, $v);
        }
        $writer->dump($long);
        $actual = file_get_contents($this->fixtureFile);
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{array<string, string>, bool, string}>
     */
    public static function dumpProvider(): iterable
    {
        yield 'short' => [
            ['foo' => 'bar', 'baz' => ''],
            false,
            "<?php declare(strict_types=1);\n\nreturn [\n    'foo' => 'bar',\n    'baz' => '',\n];\n",
        ];
        yield 'long-empty' => [
            ['foo' => ''],
            true,
            "<?php declare(strict_types=1);\n\nreturn [\n    'foo' => '',\n];\n",
        ];
        yield 'long-nonempty' => [
            ['foo' => "bar\nline2"],
            true,
            "<?php declare(strict_types=1);\n\nreturn [\n    'foo' => <<<'EOD'\nbar\nline2\nEOD\n    ,\n];\n",
        ];
    }

    public function testConstructorLoadsExistingFile(): void
    {
        file_put_contents($this->fixtureFile, '<?php return [\'foo\' => \'bar\'];');
        $writer = new ArrayWriter($this->fixtureFile);
        static::assertSame('bar', $writer->get('foo'));
    }

    public function testDumpWithClassKey(): void
    {
        $className = \stdClass::class;
        $writer = new ArrayWriter($this->fixtureFile);
        $writer->set($className, 'value');
        $writer->dump();
        $actual = file_get_contents($this->fixtureFile);
        static::assertIsString($actual);
        static::assertStringContainsString('stdClass::class', $actual);
        static::assertStringContainsString('\'value\'', $actual);

        // Also test long dump
        $writer->dump(true);
        $actualLong = file_get_contents($this->fixtureFile);
        static::assertIsString($actualLong);
        static::assertStringContainsString('stdClass::class', $actualLong);
        static::assertStringContainsString('EOD', $actualLong);
        static::assertStringContainsString('value', $actualLong);
    }
}
