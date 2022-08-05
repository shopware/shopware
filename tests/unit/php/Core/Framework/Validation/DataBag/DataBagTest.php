<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\DataBag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Validation\DataBag\DataBag
 */
class DataBagTest extends TestCase
{
    public function testConversion(): void
    {
        $bag = new DataBag([
            '1' => 'a',
            '2' => 1,
            '3' => true,
            '4' => new DataBag(['a' => 'b']),
        ]);

        static::assertEquals([
            '1' => 'a',
            '2' => 1,
            '3' => true,
            '4' => ['a' => 'b'],
        ], $bag->all());

        static::assertEquals([
            '1' => 'a',
            '2' => 1,
            '3' => true,
            '4' => ['a' => 'b'],
        ], $bag->toRequestDataBag()->all());

        static::assertEquals([
            '1' => 'a',
            '2' => 1,
            '3' => true,
        ], $bag->only('1', '2', '3'));

        static::assertEquals(['a' => 'b'], $bag->all('4'));
    }

    public function testAllWorksOnlyOnArrays(): void
    {
        $bag = new DataBag([
            '1' => 'a',
            '2' => new DataBag(['a' => 'b']),
        ]);

        static::assertEquals(['a' => 'b'], $bag->all('2'));

        $this->expectException(BadRequestException::class);
        $bag->all('1');
    }
}
