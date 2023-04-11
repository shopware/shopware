<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Search\EqualsFilterStruct;
use Shopware\Core\Framework\Store\Search\FilterStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class FilterStructClassTest extends TestCase
{
    use IntegrationTestBehaviour;

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

        static::assertInstanceOf(FilterStruct::class, $filter);
        static::assertCount(2, $filter->getQueries());

        $ratings = $filter->getQueries()[0];
        static::assertEquals('ratings', $ratings->getField());
        static::assertEquals('2', $ratings->getValue());

        $category = $filter->getQueries()[1];
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
