<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[CoversClass(ArrayStruct::class)]
class ArrayStructTest extends TestCase
{
    /**
     * @param array<string|int, mixed> $dataSet
     */
    #[DataProvider('hasDataProvider')]
    public function testHas(array $dataSet, int|string $searchFor, bool $expectFound): void
    {
        $arrayStruct = new ArrayStruct($dataSet);
        static::assertSame($expectFound, $arrayStruct->has($searchFor));
    }

    /**
     * @param array<string|int, mixed> $dataSet
     */
    #[DataProvider('hasDataProvider')]
    public function testOffsetExists(array $dataSet, int|string $searchFor, bool $expectFound): void
    {
        $arrayStruct = new ArrayStruct($dataSet);
        static::assertSame($expectFound, $arrayStruct->offsetExists($searchFor));
    }

    public function testOffsetGet(): void
    {
        $dataSet = ['a' => 'a', 'b' => ['b']];
        $arrayStruct = new ArrayStruct($dataSet);
        foreach ($dataSet as $offset => $value) {
            static::assertSame($arrayStruct->offsetGet($offset), $value);
        }
    }

    public function testOffsetSet(): void
    {
        $arrayStruct = new ArrayStruct([]);
        static::assertCount(0, $arrayStruct->all());

        $arrayStruct->offsetSet('new_value', 'some_value');

        static::assertCount(1, $arrayStruct->all(), 'the count should increment by 1');
        static::assertSame('some_value', $arrayStruct->get('new_value'));
    }

    public function testOffsetUnset(): void
    {
        $arrayStruct = new ArrayStruct(['a' => 'value']);
        static::assertCount(1, $arrayStruct->all());

        $arrayStruct->offsetUnset('a');

        static::assertCount(0, $arrayStruct->all(), 'the count should decrement by 1');
    }

    public function testGet(): void
    {
        $dataSet = ['a' => 'a', 'b' => ['b']];
        $arrayStruct = new ArrayStruct($dataSet);
        foreach ($dataSet as $offset => $value) {
            static::assertSame($arrayStruct->get($offset), $value);
        }
    }

    public function testSet(): void
    {
        $arrayStruct = new ArrayStruct([]);
        static::assertCount(0, $arrayStruct->all());

        $arrayStruct->set('new_value', 'some_value');

        static::assertCount(1, $arrayStruct->all(), 'the count should increment by 1');
        static::assertSame('some_value', $arrayStruct->get('new_value'));
    }

    public function testAssign(): void
    {
        $arrayStruct = new ArrayStruct(['a' => 'value', 'b' => ['array_value']]);

        static::assertSame('value', $arrayStruct->get('a'));
        static::assertSame(['array_value'], $arrayStruct->get('b'));

        $arrayStruct->assign(['a' => 'new_value', 'b' => null]);

        static::assertSame('new_value', $arrayStruct->get('a'));
        static::assertNull($arrayStruct->get('b'));
    }

    public function testJsonSerialize(): void
    {
        $arrayStruct = new ArrayStruct(['test' => 'value', 'date' => (new \DateTimeImmutable('2023-04-19 00:00:00'))]);

        $serializedStruct = $arrayStruct->jsonSerialize();
        static::assertArrayHasKey('test', $serializedStruct);
        static::assertArrayHasKey('date', $serializedStruct);
        static::assertSame('value', $serializedStruct['test']);
        static::assertSame('2023-04-19T00:00:00.000+00:00', $serializedStruct['date']);
    }

    public function testGetApiAlias(): void
    {
        $struct = new ArrayStruct();
        static::assertSame('array_struct', $struct->getApiAlias());

        $otherStruct = new ArrayStruct([], 'anAlias');
        static::assertSame('anAlias', $otherStruct->getApiAlias());
    }

    public function testGetVars(): void
    {
        $dataSet = ['a' => 'a', 'b' => 'b'];
        $arrayStruct = new ArrayStruct($dataSet);
        static::assertSame($dataSet, $arrayStruct->getVars());
    }

    public function testGetIterator(): void
    {
        $arrayStruct = new ArrayStruct();
        static::assertInstanceOf(\ArrayIterator::class, $arrayStruct->getIterator());
    }

    public function testCount(): void
    {
        $arrayStruct = new ArrayStruct();
        static::assertCount(0, $arrayStruct);

        $arrayStruct->set('test', 'value');
        static::assertCount(1, $arrayStruct);
    }

    public static function hasDataProvider(): \Generator
    {
        $defaultDataSet = [
            'a' => 'value',
            'b' => ['array_value'],
            94 => 44,
            'null_value' => null,
        ];

        yield 'found a' => [$defaultDataSet, 'a', true];
        yield 'found numeric 94' => [$defaultDataSet, 94, true];
        yield 'value 94 as string' => [$defaultDataSet, '94', true];
        yield 'key not existing' => [$defaultDataSet, 'not_existing', false];
        yield 'null value' => [$defaultDataSet, 'null_value', true];
    }
}
