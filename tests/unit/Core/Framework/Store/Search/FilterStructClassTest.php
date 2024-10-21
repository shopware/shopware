<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Search\EqualsFilterStruct;
use Shopware\Core\Framework\Store\Search\FilterStruct;
use Shopware\Core\Framework\Store\Search\MultiFilterStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(FilterStruct::class)]
class FilterStructClassTest extends TestCase
{
    public function testCreateInvalidType(): void
    {
        static::expectException(\InvalidArgumentException::class);
        FilterStruct::fromArray(['type' => 'invalid']);
    }

    public function testFromArray(): void
    {
        $filter = FilterStruct::fromArray([
            'type' => 'multi',
            'operator' => 'AND',
            'queries' => [
                [
                    'type' => 'equals',
                    'field' => 'ratings',
                    'value' => '2',
                ], [
                    'type' => 'equals',
                    'field' => 'category',
                    'value' => '5',
                ],
            ],
        ]);

        static::assertEquals('multi', $filter->getType());

        static::assertInstanceOf(MultiFilterStruct::class, $filter);
        static::assertCount(2, $filter->getQueries());

        $ratings = $filter->getQueries()[0];
        static::assertInstanceOf(EqualsFilterStruct::class, $ratings);
        static::assertEquals('ratings', $ratings->getField());
        static::assertEquals('2', $ratings->getValue());

        $category = $filter->getQueries()[1];
        static::assertInstanceOf(EqualsFilterStruct::class, $category);
        static::assertEquals('category', $category->getField());
        static::assertEquals('5', $category->getValue());
    }

    public function testGetQueryParametersFromMultiFilter(): void
    {
        $filter = FilterStruct::fromArray([
            'type' => 'multi',
            'operator' => 'AND',
            'queries' => [
                [
                    'type' => 'equals',
                    'field' => 'ratings',
                    'value' => '2',
                ], [
                    'type' => 'equals',
                    'field' => 'category',
                    'value' => '5',
                ],
            ],
        ]);

        static::assertEquals([
            'ratings' => '2',
            'category' => '5',
        ], $filter->getQueryParameter());
    }

    public function testSetterFromEquals(): void
    {
        $filter = new EqualsFilterStruct();
        $filter->setField('field');
        $filter->setValue('value');

        static::assertEquals('field', $filter->getField());
        static::assertEquals('value', $filter->getValue());
    }
}
