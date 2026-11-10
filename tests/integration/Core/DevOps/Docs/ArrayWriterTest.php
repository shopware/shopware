<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\Docs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\ArrayWriter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ArrayWriterTest extends TestCase
{
    private Filesystem $filesystem;

    private string $outputDir;

    private string $outputFile;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->outputDir = sys_get_temp_dir() . '/' . uniqid('shopware-array-writer-test', true);
        $this->filesystem->mkdir($this->outputDir);
        $this->outputFile = $this->outputDir . '/array.php';
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->outputDir);
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('dumpProvider')]
    public function testDump(array $data, bool $long, string $expected): void
    {
        $writer = new ArrayWriter($this->outputFile);
        foreach ($data as $k => $v) {
            $writer->set($k, $v);
        }
        $writer->dump($long);

        static::assertSame($expected, $this->filesystem->readFile($this->outputFile));
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

    public function testDumpWithClassKey(): void
    {
        $className = \stdClass::class;
        $writer = new ArrayWriter($this->outputFile);
        $writer->set($className, 'value');
        $writer->dump();
        $actual = $this->filesystem->readFile($this->outputFile);
        static::assertStringContainsString('stdClass::class', $actual);
        static::assertStringContainsString('\'value\'', $actual);

        // Also test long dump
        $writer->dump(true);
        $actualLong = $this->filesystem->readFile($this->outputFile);
        static::assertStringContainsString('stdClass::class', $actualLong);
        static::assertStringContainsString('EOD', $actualLong);
        static::assertStringContainsString('value', $actualLong);
    }
}
