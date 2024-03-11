<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Twig\DoctrineExtension;

/**
 * @internal
 */
#[CoversClass(DoctrineExtension::class)]
class DoctrineExtensionTest extends TestCase
{
    public function testReplaceQueryParametersWithPostgresCasting(): void
    {
        $extension = new DoctrineExtension();
        $query = 'a=? OR (1)::string OR b=?';
        $parameters = [1, 2];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertEquals('a=1 OR (1)::string OR b=2', $result);
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
        static::assertEquals('a=1 OR b=2', $result);
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
        static::assertEquals('a=1 OR b=2', $result);
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
        static::assertEquals('a=1 OR b=2', $result);
    }

    public function testReplaceQueryParametersWithEmptyArray(): void
    {
        $extension = new DoctrineExtension();
        $query = 'IN (?)';
        $parameters = [
            [],
        ];

        $result = $extension->replaceQueryParameters($query, $parameters);
        static::assertEquals('IN (NULL)', $result);
    }

    public function testEscapeBinaryParameter(): void
    {
        $binaryString = pack('H*', '9d40b8c1417f42d099af4782ec4b20b6');
        static::assertEquals('0x9D40B8C1417F42D099AF4782EC4B20B6', DoctrineExtension::escapeFunction($binaryString));
    }

    public function testEscapeStringParameter(): void
    {
        static::assertEquals('\'test string\'', DoctrineExtension::escapeFunction('test string'));
    }

    public function testEscapeArrayParameter(): void
    {
        static::assertEquals('1, NULL, \'test\', foo, NULL', DoctrineExtension::escapeFunction([1, null, 'test', new DummyClass('foo'), []]));
    }

    public function testEscapeObjectParameter(): void
    {
        $object = new DummyClass('bar');
        static::assertEquals('bar', DoctrineExtension::escapeFunction($object));
    }

    public function testEscapeNullParameter(): void
    {
        static::assertEquals('NULL', DoctrineExtension::escapeFunction(null));
    }

    public function testEscapeBooleanParameter(): void
    {
        static::assertEquals('1', DoctrineExtension::escapeFunction(true));
    }

    public function testItUsesCssOnThePreTag(): void
    {
        $extension = new DoctrineExtension();
        static::assertSame(
            1,
            substr_count($extension->formatSQL('CREATE DATABASE 📚;', true), '<pre class=')
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
