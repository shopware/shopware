<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodRoute::class)]
class ShippingMethodRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new ShippingMethodRoute($this->createMock(SalesChannelRepository::class), new EventDispatcher(), $this->createMock(ScriptExecutor::class));

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testLoad(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $expectedCriteria = clone $criteria;
        $expectedCriteria->addFilter(new EqualsFilter('active', true));
        $expectedCriteria->addSorting(new FieldSorting('position'), new FieldSorting('name', FieldSorting::ASCENDING));
        $expectedCriteria->addAssociation('media');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier('foo');

        $result = new EntitySearchResult(
            'shipping_method',
            1,
            $entities = new ShippingMethodCollection([$shippingMethod]),
            null,
            $expectedCriteria,
            $context->getContext()
        );

        $repo = $this->createMock(SalesChannelRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $route = new ShippingMethodRoute($repo, new EventDispatcher(), $this->createMock(ScriptExecutor::class));

        $response = $route->load($request, $context, $criteria);

        static::assertSame($entities, $response->getShippingMethods());
    }

    public function testOnlyAvailableFlag(): void
    {
        $request = new Request();
        $request->query->set('onlyAvailable', true);
        $context = Generator::generateSalesChannelContext();
        $context->setRuleIds(['rule_2']);
        $criteria = new Criteria();

        $expectedCriteria = clone $criteria;
        $expectedCriteria->addFilter(new EqualsFilter('active', true));
        $expectedCriteria->addFilter(new RangeFilter('prices.currencyPrice', [RangeFilter::GTE => -\PHP_INT_MAX]));
        $expectedCriteria->addSorting(new FieldSorting('position'), new FieldSorting('name', FieldSorting::ASCENDING));
        $expectedCriteria->addAssociation('media');

        $shippingMethod1 = new ShippingMethodEntity();
        $shippingMethod1->setUniqueIdentifier('rule_1');
        $shippingMethod1->setAvailabilityRuleId('rule_1');

        $shippingMethod2 = new ShippingMethodEntity();
        $shippingMethod2->setUniqueIdentifier('rule_2');
        $shippingMethod2->setAvailabilityRuleId('rule_2');

        $result = new EntitySearchResult(
            'shipping_method',
            2,
            new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]),
            null,
            $expectedCriteria,
            $context->getContext()
        );

        $repo = $this->createMock(SalesChannelRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $route = new ShippingMethodRoute($repo, new EventDispatcher(), $this->createMock(ScriptExecutor::class));

        $response = $route->load($request, $context, $criteria);

        $shippingMethods = $response->getShippingMethods();

        static::assertCount(1, $shippingMethods);
        static::assertSame('rule_2', $shippingMethods->first()?->getUniqueIdentifier());
    }

    public function testOnlyAvailableExcludesShippingMethodsWithoutAnyPrice(): void
    {
        $request = new Request();
        $request->query->set('onlyAvailable', true);
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $usedCriteria = null;

        $repo = $this->createMock(SalesChannelRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $context) use (&$usedCriteria) {
                $usedCriteria = $criteria;

                return new EntitySearchResult(
                    'shipping_method',
                    0,
                    new ShippingMethodCollection([]),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $route = new ShippingMethodRoute($repo, new EventDispatcher(), static::createStub(ScriptExecutor::class));

        $route->load($request, $context, $criteria);

        static::assertInstanceOf(Criteria::class, $usedCriteria);
        static::assertContainsEquals(
            new RangeFilter('prices.currencyPrice', [RangeFilter::GTE => -\PHP_INT_MAX]),
            $usedCriteria->getFilters(),
        );
    }

    public function testLoadDoesNotRestrictPricesWithoutOnlyAvailable(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $usedCriteria = null;

        $repo = $this->createMock(SalesChannelRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $context) use (&$usedCriteria) {
                $usedCriteria = $criteria;

                return new EntitySearchResult(
                    'shipping_method',
                    0,
                    new ShippingMethodCollection([]),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $route = new ShippingMethodRoute($repo, new EventDispatcher(), static::createStub(ScriptExecutor::class));

        $route->load($request, $context, $criteria);

        static::assertInstanceOf(Criteria::class, $usedCriteria);

        $priceFilters = array_filter(
            $usedCriteria->getFilters(),
            static fn (Filter $filter) => $filter instanceof RangeFilter && $filter->getField() === 'prices.currencyPrice',
        );

        static::assertSame([], $priceFilters);
    }
}
