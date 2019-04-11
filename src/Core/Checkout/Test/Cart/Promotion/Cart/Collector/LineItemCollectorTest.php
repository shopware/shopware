<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Cart\Collector;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Collector\LineItemCollector;
use Shopware\Core\Checkout\Promotion\Cart\Validator\LineIemCollector;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemCollectorTest extends TestCase
{
    /** @var LineIemCollector */
    private $validator = null;

    /** @var Cart */
    private $cart = null;

    /** @var MockObject */
    private $checkoutContext = null;

    /**
     * @throws \ReflectionException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function setUp(): void
    {
        $this->cart = new Cart('C1', 'TOKEN-1');
        $this->cart->add(new LineItem('P1', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));
        $this->cart->add(new LineItem('P2', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));

        $this->checkoutContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        $this->validator = new LineItemCollector('PROMOTION');
    }
}
