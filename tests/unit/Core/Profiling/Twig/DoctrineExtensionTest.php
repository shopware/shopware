<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Twig\DoctrineExtension;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DoctrineExtension::class)]
class DoctrineExtensionTest extends TestCase
{
    public function testReplaceQueryParametersWithPostgresCasting(): void
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR (1)::string OR b=?';
        $parameters = [1, 2];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertSame('a=1 OR (1)::string OR b=2', $result);
    }

    public function testReplaceQueryParametersWithStartingIndexAtOne(): void
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR b=?';
        $parameters = [
            1 => 1,
            2 => 2,
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertSame('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParameters(): void
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR b=?';
        $parameters = [
            1,
            2,
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertSame('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParametersWithNamedIndex(): void
    {
        $extension = new DoctrineExtension();
        $query = 'a=:a OR b=:b';
        $parameters = [
            'a' => 1,
            'b' => 2,
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertSame('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParametersWithEmptyArray(): void
    {
        $extension = new DoctrineExtension();
        $query = 'IN (?)';
        $parameters = [
            [],
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertSame('IN (NULL)', $result);
    }

    public function testEscapeBinaryParameter(): void
    {
        $binaryString = pack('H*', '9d40b8c1417f42d099af4782ec4b20b6');
        static::assertSame('0x9D40B8C1417F42D099AF4782EC4B20B6', DoctrineExtension::escapeFunction($binaryString));
    }

    public function testEscapeStringParameter(): void
    {
        static::assertSame('\'test string\'', DoctrineExtension::escapeFunction('test string'));
    }

    public function testEscapeArrayParameter(): void
    {
        static::assertSame('1, NULL, \'test\', foo, NULL', DoctrineExtension::escapeFunction([1, null, 'test', new DummyClass('foo'), []]));
    }

    public function testEscapeObjectParameter(): void
    {
        $object = new DummyClass('bar');
        static::assertSame('bar', DoctrineExtension::escapeFunction($object));
    }

    public function testEscapeNullParameter(): void
    {
        static::assertSame('NULL', DoctrineExtension::escapeFunction(null));
    }

    public function testEscapeBooleanParameter(): void
    {
        static::assertSame('1', DoctrineExtension::escapeFunction(true));
    }

    public function testBinaryToHexConvertsBinaryValuesOnly(): void
    {
        $id = pack('H*', '9d40b8c1417f42d099af4782ec4b20b6');
        $otherId = pack('H*', '0190ad8bba7c7ba5a0ed2b2e35d6c0b4');

        $result = (new DoctrineExtension())->binaryToHex([$id, [$id, $otherId], 'shirt', 3, null]);

        static::assertSame(
            ['9d40b8c1417f42d099af4782ec4b20b6', ['9d40b8c1417f42d099af4782ec4b20b6', '0190ad8bba7c7ba5a0ed2b2e35d6c0b4'], 'shirt', 3, null],
            $result->getValue(true)
        );
    }

    public function testBinaryToHexReadsClonedParameters(): void
    {
        $parameters = (new VarCloner())->cloneVar(['id' => pack('H*', '9d40b8c1417f42d099af4782ec4b20b6')]);

        static::assertSame(['id' => '9d40b8c1417f42d099af4782ec4b20b6'], (new DoctrineExtension())->binaryToHex($parameters)->getValue(true));
    }

    public function testBinaryToHexKeepsParametersWithoutBinaryValues(): void
    {
        $parameters = (new VarCloner())->cloneVar(['shirt', 3, new DummyClass('foo')]);

        static::assertSame($parameters, (new DoctrineExtension())->binaryToHex($parameters));
    }

    public function testBinaryToHexKeepsLongLists(): void
    {
        $ids = array_fill(0, 3000, pack('H*', '9d40b8c1417f42d099af4782ec4b20b6'));

        $result = (new DoctrineExtension())->binaryToHex([$ids])->getValue(true);

        static::assertIsArray($result);
        static::assertIsArray($result[0]);
        static::assertCount(3000, $result[0]);
    }

    public function testItUsesCssOnThePreTag(): void
    {
        $extension = new DoctrineExtension();
        static::assertSame(
            1,
            substr_count($extension->formatSql('CREATE DATABASE 📚;', true), '<pre class=')
        );
    }
}

/**
 * @internal
 */
class DummyClass implements \Stringable
{
    public function __construct(protected string $str)
    {
    }

    public function __toString(): string
    {
        return $this->str;
    }
}
