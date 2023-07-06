<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Common\Stubs\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor
 */
class SortingProcessorTest extends TestCase
{
    /**
     * @dataProvider prepareProvider
     *
     * @param FieldSorting[] $expected
     */
    public function testPrepare(string $requested, array $expected): void
    {
        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.defaultSorting' => 'foo',
            ]),
            new StaticEntityRepository([$this->buildSortings()])
        );

        $processor->prepare(
            new Request(['order' => $requested]),
            $criteria = new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals($expected, $criteria->getSorting());
    }

    public function testProcess(): void
    {
        $sortings = $this->buildSortings();

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.defaultSorting' => 'foo',
            ]),
            new StaticEntityRepository([
                $sortings,
            ])
        );

        $result = new ProductListingResult('foo', 1, new ProductCollection(), null, new Criteria(), Context::createDefaultContext());
        $result->getCriteria()->addExtension('sortings', $sortings);

        $processor->process(
            new Request(['order' => 'foo']),
            $result,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals('foo', $result->getSorting());
    }

    /**
     * @dataProvider unknownSortingProvider
     */
    public function testUnknownSortingThrowsException(mixed $requested): void
    {
        $this->expectException(ProductException::class);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.defaultSorting' => 'foo',
            ]),
            new StaticEntityRepository([
                $this->buildSortings(),
            ])
        );

        $processor->prepare(
            new Request(['order' => $requested]),
            new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public static function prepareProvider(): \Generator
    {
        yield 'Requested foo sorting will be accepted' => [
            'foo',
            [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('foo', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested bar sorting will be accepted' => [
            'bar',
            [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('bar', FieldSorting::DESCENDING),
            ],
        ];
    }

    public static function unknownSortingProvider(): \Generator
    {
        yield 'Requested unknown sorting will throw exception' => ['unknown'];
        yield 'Requested empty sorting will throw exception' => [''];
        yield 'Requested null sorting will throw exception' => [null];
        yield 'Requested empty array sorting will throw exception' => [[]];
    }

    private function buildSortings(): ProductSortingCollection
    {
        return new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'foo',
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ]);
    }
}
