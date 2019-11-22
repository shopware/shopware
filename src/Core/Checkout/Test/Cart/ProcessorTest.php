<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var SalesChannelContextFactory
     */
    private $factory;

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var Processor
     */
    private $processor;

    protected function setUp(): void
    {
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->factory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->context = $this->factory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testDeliveryCreatedForDeliverableLineItem(): void
    {
        $cart = new Cart('test', 'test');

        $id = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $product = [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->context, $tax);

        $cart->add(
            (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getLineItems());
        static::assertTrue($calculated->has($id));
        static::assertSame(119.99, $calculated->get($id)->getPrice()->getTotalPrice());

        static::assertCount(1, $calculated->getDeliveries());

        /** @var Delivery $delivery */
        $delivery = $calculated->getDeliveries()->first();
        static::assertTrue($delivery->getPositions()->getLineItems()->has($id));
    }
}
