<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FindProductVariantRoute::class)]
class FindProductVariantRouteTest extends TestCase
{
    private MockObject&SalesChannelRepository $productRepositoryMock;

    private FindProductVariantRoute $route;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->productRepositoryMock = $this->createMock(SalesChannelRepository::class);
        $this->route = new FindProductVariantRoute($this->productRepositoryMock);
        $this->ids = new IdsCollection();
    }

    public function testNoDecoration(): void
    {
        static::expectException(DecorationPatternException::class);
        static::expectExceptionMessage(
            'The getDecorated() function of core class ' . FindProductVariantRoute::class
            . ' cannot be used. This class is the base class.'
        );

        $this->route->getDecorated();
    }

    public function testLoad(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $this->productRepositoryMock->method('searchIds')->with(
            $criteria,
            $this->createMock(SalesChannelContext::class),
        )
            ->willReturn(
                new IdSearchResult(
                    2,
                    [
                        [
                            'primaryKey' => $this->ids->get('found1'),
                            'data' => [],
                        ],
                        [
                            'primaryKey' => $this->ids->get('found2'),
                            'data' => [],
                        ],
                    ],
                    $criteria,
                    $context
                )
            );

        $response = $this->route->load($this->ids->get('productId'), $request, $this->createMock(SalesChannelContext::class));

        static::assertInstanceOf(FindProductVariantRouteResponse::class, $response);
        static::assertEquals($this->ids->get('found1'), $response->getFoundCombination()->getVariantId());
        static::assertEquals($options, $response->getFoundCombination()->getOptions());
    }

    public function testLoadFirstVariantNotFound(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $criteria2 = new Criteria();
        $criteria2->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria2->setLimit(1);
        $criteria2->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $this->productRepositoryMock->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria,
                    $context
                ),
                new IdSearchResult(
                    1,
                    [
                        [
                            'primaryKey' => $this->ids->get('found1'),
                            'data' => [],
                        ],
                    ],
                    $criteria2,
                    $context
                ),
            );

        $response = $this->route->load($this->ids->get('productId'), $request, $this->createMock(SalesChannelContext::class));

        static::assertInstanceOf(FindProductVariantRouteResponse::class, $response);
        static::assertEquals($this->ids->get('found1'), $response->getFoundCombination()->getVariantId());
    }

    public function testLoadNoVariantFound(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $criteria2 = new Criteria();
        $criteria2->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria2->setLimit(1);
        $criteria2->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $this->productRepositoryMock->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria,
                    $context
                ),
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria2,
                    $context
                ),
            );

        static::expectException(VariantNotFoundException::class);
        static::expectExceptionMessage(
            'Variant for productId ' . $this->ids->get('productId') . ' with options {"' . $this->ids->get('group2')
            . '":"' . $this->ids->get('option2') . '"} not found.'
        );

        try {
            $this->route->load($this->ids->get('productId'), $request, $this->createMock(SalesChannelContext::class));
        } catch (VariantNotFoundException $e) {
            static::assertEquals('CONTENT__PRODUCT_VARIANT_NOT_FOUND', $e->getErrorCode());

            throw $e;
        }
    }
}
