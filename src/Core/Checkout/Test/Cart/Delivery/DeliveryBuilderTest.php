<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class DeliveryBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var DeliveryBuilder
     */
    private $builder;

    /**
     * @var \Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    private $context;

    /**
     * @var DeliveryProcessor
     */
    private $processor;

    protected function setUp(): void
    {
        $this->builder = $this->getContainer()->get(DeliveryBuilder::class);

        $this->processor = $this->getContainer()->get(DeliveryProcessor::class);

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testEmptyCart(): void
    {
        $cart = new Cart('test', 'test');

        $data = new CartDataCollection();

        $behavior = new CartBehavior();

        $this->processor->collect($data, $cart, $this->context, $behavior);

        $deliveries = $this->builder->build($cart, $data, $this->context, $behavior);

        static::assertCount(0, $deliveries);
    }
}
