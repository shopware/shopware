<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DataProviderRowArityFixture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

class DataProviderRowArityCases extends TestCase
{
    #[DataProvider('attributeOvershootProvider')]
    public function testAttributeOvershoot(string $a, string $b): void
    {
        static::assertSame($a, $b);
    }

    public static function attributeOvershootProvider(): \Generator
    {
        yield 'too wide' => ['a', 'b', 'c', 'd'];
        yield 'fine' => ['a', 'b'];
    }

    #[DataProvider('compliantProvider')]
    public function testCompliant(string $a, string $b): void
    {
        static::assertSame($a, $b);
    }

    public static function compliantProvider(): \Generator
    {
        yield 'ok' => ['a', 'b'];
        yield 'also ok' => ['a', 'b'];
    }

    /**
     * @dataProvider annotationOvershootProvider
     */
    public function testAnnotationOvershoot(string $a): void
    {
        static::assertSame($a, $a);
    }

    public static function annotationOvershootProvider(): \Generator
    {
        yield 'wide' => ['a', 'b', 'c'];
    }

    #[DataProvider('returnArrayProvider')]
    public function testReturnArrayOvershoot(string $a): void
    {
        static::assertSame($a, $a);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function returnArrayProvider(): array
    {
        return [
            'ok' => ['a'],
            'bad' => ['a', 'b'],
        ];
    }

    #[DataProvider('spreadProvider')]
    public function testSpreadProvider(string $a): void
    {
        static::assertSame($a, $a);
    }

    public static function spreadProvider(): \Generator
    {
        $extra = ['b', 'c'];
        yield 'spread' => ['a', ...$extra];
    }

    #[DataProviderExternal(SomeExternalProviderClass::class, 'externalProvider')]
    public function testExternalProvider(string $a): void
    {
        static::assertSame($a, $a);
    }
}

class SomeExternalProviderClass
{
    public static function externalProvider(): \Generator
    {
        yield 'wide' => ['a', 'b', 'c'];
    }
}
