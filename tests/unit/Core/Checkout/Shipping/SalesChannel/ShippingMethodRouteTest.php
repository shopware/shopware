<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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
        $route = new ShippingMethodRoute(
            $this->createMock(SalesChannelRepository::class),
            new EventDispatcher(),
            $this->createMock(ScriptExecutor::class),
            new RuleIdMatcher(),
        );

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
            ->expects(static::once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $route = new ShippingMethodRoute(
            $repo,
            new EventDispatcher(),
            $this->createMock(ScriptExecutor::class),
            new RuleIdMatcher()
        );

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
            $entities = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]),
            null,
            $expectedCriteria,
            $context->getContext()
        );

        $repo = $this->createMock(SalesChannelRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context)
            ->willReturn($result);

        $hook = new ShippingMethodRouteHook(new ShippingMethodCollection([$shippingMethod2]), true, $context);

        $executor = $this->createMock(ScriptExecutor::class);
        $executor
            ->expects(static::once())
            ->method('execute')
            ->with(static::equalTo($hook));

        $route = new ShippingMethodRoute($repo, new EventDispatcher(), $executor, new RuleIdMatcher());

        $response = $route->load($request, $context, $criteria);

        $shippingMethods = $response->getShippingMethods();

        static::assertCount(1, $shippingMethods);
        static::assertSame('rule_2', $shippingMethods->first()?->getUniqueIdentifier());
    }
}
