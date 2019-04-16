<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\DiscountSurcharge\Cart\DiscountSurchargeCollector;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DiscountSurchargeTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    public static $discountSurchargeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    public static $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    public static $productRepository;

    /**
     * @var SalesChannelContext
     */
    public static $context;

    /**
     * @var Processor
     */
    public static $processor;

    /**
     * @var Enrichment
     */
    public static $enrichment;

    /**
     * @var SalesChannelContextFactory
     */
    public static $factory;

    /**
     * @var Connection
     */
    public static $connection;

    protected function setUp(): void
    {
        self::$ruleRepository = $this->getContainer()->get('rule.repository');

        self::$connection = $this->getContainer()->get(Connection::class);

        self::$productRepository = $this->getContainer()->get('product.repository');
        self::$discountSurchargeRepository = $this->getContainer()->get('discount_surcharge.repository');
        self::$factory = $this->getContainer()->get(SalesChannelContextFactory::class);

        self::$context = self::$factory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        self::$processor = $this->getContainer()->get(Processor::class);
        self::$enrichment = $this->getContainer()->get(Enrichment::class);
    }

    public function testAbsoulteSurcharge(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 31, 31, 19);

        $ruleId = $this->createRule();

        $id = $this->createDiscountSurcharge($ruleId, DiscountSurchargeCollector::ABSOLUTE_MODIFIER, 2);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $productA])
        );
        $cart->add(
            (new LineItem('B', 'product'))
                ->setPayload(['id' => $productB])
        );

        self::$context->setRuleIds([$ruleId]);
        self::$enrichment->enrich($cart, self::$context, new CartBehavior());

        $key = 'discount-surcharge-' . $id;
        static::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context, new CartBehavior());

        static::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        static::assertInstanceOf(LineItem::class, $discount);
        static::assertEquals(2.0, $discount->getPrice()->getTotalPrice());
        static::assertEquals(1, $discount->getQuantity());

        static::assertEquals(
            [new CalculatedTax(0.32, 19, 2)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testAbsoluteDiscount(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 31, 31, 19);

        $ruleId = $this->createRule();

        $id = $this->createDiscountSurcharge($ruleId, DiscountSurchargeCollector::ABSOLUTE_MODIFIER, -2);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $productA])
        );
        $cart->add(
            (new LineItem('B', 'product'))
                ->setPayload(['id' => $productB])
        );

        self::$context->setRuleIds([$ruleId]);
        self::$enrichment->enrich($cart, self::$context, new CartBehavior());

        $key = 'discount-surcharge-' . $id;
        static::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context, new CartBehavior());

        static::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        static::assertInstanceOf(LineItem::class, $discount);
        static::assertEquals(-2.0, $discount->getPrice()->getTotalPrice());
        static::assertEquals(1, $discount->getQuantity());

        static::assertEquals(
            [new CalculatedTax(-0.32, 19, -2)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testPercentageSurcharge(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 30, 30, 19);

        $ruleId = $this->createRule();

        $id = $this->createDiscountSurcharge($ruleId, DiscountSurchargeCollector::PERCENTAL_MODIFIER, 10);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $productA])
        );
        $cart->add(
            (new LineItem('B', 'product'))
                ->setPayload(['id' => $productB])
        );

        self::$context->setRuleIds([$ruleId]);
        self::$enrichment->enrich($cart, self::$context, new CartBehavior());

        $key = 'discount-surcharge-' . $id;
        static::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context, new CartBehavior());

        static::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        static::assertInstanceOf(LineItem::class, $discount);
        static::assertEquals(5.0, $discount->getPrice()->getTotalPrice());
        static::assertEquals(1, $discount->getQuantity());

        static::assertEquals(
            [new CalculatedTax(0.8, 19, 5)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testPercentageDiscount(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 30, 30, 19);

        $ruleId = $this->createRule();

        $id = $this->createDiscountSurcharge($ruleId, DiscountSurchargeCollector::PERCENTAL_MODIFIER, -10);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $productA])
        );
        $cart->add(
            (new LineItem('B', 'product'))
                ->setPayload(['id' => $productB])
        );

        self::$context->setRuleIds([$ruleId]);
        self::$enrichment->enrich($cart, self::$context, new CartBehavior());

        $key = 'discount-surcharge-' . $id;
        static::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context, new CartBehavior());

        static::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        static::assertInstanceOf(LineItem::class, $discount);
        static::assertEquals(-5.0, $discount->getPrice()->getTotalPrice());
        static::assertEquals(1, $discount->getQuantity());

        static::assertEquals(
            [new CalculatedTax(-0.8, 19, -5)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    private function createRule(string $name = 'Test rule', int $priority = 1): string
    {
        $id = Uuid::randomHex();

        self::$ruleRepository->upsert(
            [
                [
                    'id' => $id,
                    'name' => $name,
                    'priority' => $priority,
                ],
            ],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function createDiscountSurcharge(string $ruleId, string $type, float $amount, ?Rule $rule = null): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => sprintf('Test modifier (%s)', $type),
            'ruleId' => $ruleId,
            'type' => $type,
            'amount' => $amount,
        ];

        self::$discountSurchargeRepository->upsert([array_filter($data)], Context::createDefaultContext());

        return $id;
    }

    private function createProduct(string $name, float $netPrice, float $grossPrice, float $taxRate): string
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();

        self::$productRepository->upsert([
            [
                'id' => $id,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => $name,
                'manufacturer' => ['name' => 'test'],
                'price' => ['gross' => $grossPrice, 'net' => $netPrice, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => $taxRate],
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
