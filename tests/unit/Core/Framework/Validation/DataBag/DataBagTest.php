<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\DataBag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * @internal
 */
#[CoversClass(DataBag::class)]
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

    public function testAddSetConvertsToDataBag(): void
    {
        $bag = new DataBag();

        $bag->set('1', new DataBag(['a' => 'b']));

        static::assertInstanceOf(DataBag::class, $bag->get('1'));
        static::assertSame(['a' => 'b'], $bag->get('1')->all());
    }

    public function testSetDataBag(): void
    {
        $bag = new DataBag();

        $bag->set('2', ['a' => 'b']);

        static::assertInstanceOf(DataBag::class, $bag->get('2'));
        static::assertSame(['a' => 'b'], $bag->get('2')->all());
    }

    public function testAddDataBag(): void
    {
        $bag = new DataBag();

        $bag->add([
            '3' => new DataBag(['a' => 'b']),
        ]);

        static::assertInstanceOf(DataBag::class, $bag->get('3'));
        static::assertSame(['a' => 'b'], $bag->get('3')->all());
    }

    public function testAddArrayConvertsToDataBag(): void
    {
        $bag = new DataBag();

        $bag->add([
            '4' => ['a' => 'b'],
        ]);

        static::assertInstanceOf(DataBag::class, $bag->get('4'));
        static::assertSame(['a' => 'b'], $bag->get('4')->all());
    }

    public function testEnsureCloningIsDeep(): void
    {
        $bag = new DataBag();
        $innerBag = new DataBag();

        $bag->set('key', $innerBag);

        static::assertSame($innerBag, $bag->get('key'));

        $clone = clone $bag;

        static::assertNotSame($innerBag, $clone->get('key'));
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
