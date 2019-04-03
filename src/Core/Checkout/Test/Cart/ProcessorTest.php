<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class ProcessorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var SalesChannelContextFactory
     */
    private $factory;

    /**
     * @var CheckoutContext
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

    public function testAddOwnLineItem(): void
    {
        $cart = new Cart('test', 'test');

        $cart->add(
            (new LineItem('A', 'test'))
                ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 2))
        );

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getLineItems());
        static::assertTrue($calculated->has('A'));
        static::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
    }

    public function testDeliveryCreatedForDeliverableLineItem(): void
    {
        $cart = new Cart('test', 'test');

        $cart->add(
            (new LineItem('A', 'test'))
                ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 2))
                ->setDeliveryInformation(
                    new DeliveryInformation(
                        100,
                        100,
                        new DeliveryDate(new \DateTime(), new \DateTime()),
                        new DeliveryDate(new \DateTime(), new \DateTime()),
                        false
                    )
                )
        );

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getLineItems());
        static::assertTrue($calculated->has('A'));
        static::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());

        static::assertCount(1, $calculated->getDeliveries());

        /** @var Delivery $delivery */
        $delivery = $calculated->getDeliveries()->first();
        static::assertTrue($delivery->getPositions()->getLineItems()->has('A'));
    }
}
