<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(FlowRuleScopeBuilder::class)]
class FlowRuleScopeBuilderTest extends TestCase
{
    private MockObject&OrderConverter $orderConverter;

    private MockObject&DeliveryBuilder $deliveryBuilder;

    private MockObject&CartDataCollectorInterface $cartDataCollector;

    private FlowRuleScopeBuilder $scopeBuilder;

    protected function setUp(): void
    {
        $this->orderConverter = $this->createMock(OrderConverter::class);
        $this->deliveryBuilder = $this->createMock(DeliveryBuilder::class);
        $this->cartDataCollector = $this->createMock(CartDataCollectorInterface::class);
        $this->scopeBuilder = new FlowRuleScopeBuilder($this->orderConverter, $this->deliveryBuilder, [$this->cartDataCollector]);
    }

    public function testBuild(): void
    {
        $mockContext = $this->createMock(SalesChannelContext::class);
        $cart = new Cart('test');
        $this->orderConverter->method('assembleSalesChannelContext')->willReturn($mockContext);
        $this->orderConverter->method('convertToCart')->willReturn($cart);
        $this->deliveryBuilder->method('build')->willReturn(new DeliveryCollection());
        $this->cartDataCollector->expects(static::exactly(2))->method('collect');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $expected = new FlowRuleScope($order, $cart, $mockContext);
        $context = Context::createDefaultContext();

        static::assertEquals($expected, $this->scopeBuilder->build($order, $context));
        static::assertEquals($expected, $this->scopeBuilder->build($order, $context));

        $this->scopeBuilder->reset();

        static::assertEquals($expected, $this->scopeBuilder->build($order, $context));
    }
}
