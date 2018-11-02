<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\DiscountSurcharge\Cart\DiscountSurchargeCollector;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class DiscountSurchargeTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var RepositoryInterface
     */
    public static $discountSurchargeRepository;

    /**
     * @var RepositoryInterface
     */
    public static $ruleRepository;

    /**
     * @var RepositoryInterface
     */
    public static $productRepository;

    /**
     * @var CheckoutContext
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
     * @var CheckoutContextFactory
     */
    public static $factory;

    /**
     * @var Connection
     */
    public static $connection;

    public function setUp()
    {
        self::$ruleRepository = $this->getContainer()->get('rule.repository');

        self::$connection = $this->getContainer()->get(Connection::class);

        self::$productRepository = $this->getContainer()->get('product.repository');
        self::$discountSurchargeRepository = $this->getContainer()->get('discount_surcharge.repository');
        self::$factory = $this->getContainer()->get(CheckoutContextFactory::class);

        self::$context = self::$factory->create(Defaults::TENANT_ID, Defaults::TENANT_ID, Defaults::SALES_CHANNEL);

        self::$processor = $this->getContainer()->get(Processor::class);
        self::$enrichment = $this->getContainer()->get(Enrichment::class);
    }

    public function testAbsoulteSurcharge(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 31, 31, 19);

        $ruleId = $this->createRule([]);

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
        self::$enrichment->enrich($cart, self::$context);

        $key = 'discount-surcharge-' . $id;
        self::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context);

        self::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(2.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());

        self::assertEquals(
            [new CalculatedTax(0.32, 19, 2)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testAbsoluteDiscount(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 31, 31, 19);

        $ruleId = $this->createRule([]);

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
        self::$enrichment->enrich($cart, self::$context);

        $key = 'discount-surcharge-' . $id;
        self::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context);

        self::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(-2.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());

        self::assertEquals(
            [new CalculatedTax(-0.32, 19, -2)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testPercentageSurcharge(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 30, 30, 19);

        $ruleId = $this->createRule([]);

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
        self::$enrichment->enrich($cart, self::$context);

        $key = 'discount-surcharge-' . $id;
        self::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context);

        self::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(5.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());

        self::assertEquals(
            [new CalculatedTax(0.8, 19, 5)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testPercentageDiscount(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 30, 30, 19);

        $ruleId = $this->createRule([]);

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
        self::$enrichment->enrich($cart, self::$context);

        $key = 'discount-surcharge-' . $id;
        self::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context);

        self::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(-5.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());

        self::assertEquals(
            [new CalculatedTax(-0.8, 19, -5)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testAbsoluteConsidersFilter(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 31, 31, 7);

        $ruleId = $this->createRule([]);

        $id = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeCollector::ABSOLUTE_MODIFIER,
            -2,
            new LineItemRule(['A'])
        );

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
        self::$enrichment->enrich($cart, self::$context);

        $key = 'discount-surcharge-' . $id;
        self::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context);

        self::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(-2.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());

        self::assertEquals(
            [new CalculatedTax(-0.32, 19, -2)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testPercentageConsidersFilter(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 30, 30, 19);

        $ruleId = $this->createRule([]);

        $id = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeCollector::PERCENTAL_MODIFIER,
            -10,
            new LineItemRule(['B'])
        );

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
        self::$enrichment->enrich($cart, self::$context);

        $key = 'discount-surcharge-' . $id;
        self::assertTrue($cart->has($key));

        $calculated = self::$processor->process($cart, self::$context);

        self::assertTrue($calculated->has($key));

        $discount = $calculated->get($key);

        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(-3.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());

        self::assertEquals(
            [new CalculatedTax(-0.48, 19, -3)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    public function testMultipleDiscounts(): void
    {
        $productA = $this->createProduct('Product A', 20, 20, 19);
        $productB = $this->createProduct('Product B', 30, 30, 19);

        $ruleId = $this->createRule([]);

        $id1 = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeCollector::PERCENTAL_MODIFIER,
            -10,
            new LineItemOfTypeRule('product')
        );

        $id2 = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeCollector::PERCENTAL_MODIFIER,
            10,
            new LineItemOfTypeRule('product')
        );

        $id3 = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeCollector::ABSOLUTE_MODIFIER,
            2,
            new LineItemOfTypeRule('product')
        );

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
        self::$enrichment->enrich($cart, self::$context);

        $calculated = self::$processor->process($cart, self::$context);

        $key = 'discount-surcharge-' . $id1;
        self::assertTrue($calculated->has($key));
        $discount = $calculated->get($key);
        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(-5.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());
        self::assertEquals(
            [new CalculatedTax(-0.8, 19, -5)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );

        $key = 'discount-surcharge-' . $id2;
        self::assertTrue($calculated->has($key));
        $discount = $calculated->get($key);
        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(5.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());
        self::assertEquals(
            [new CalculatedTax(0.8, 19, 5)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );

        $key = 'discount-surcharge-' . $id3;
        self::assertTrue($calculated->has($key));
        $discount = $calculated->get($key);
        self::assertInstanceOf(LineItem::class, $discount);
        self::assertEquals(2.0, $discount->getPrice()->getTotalPrice());
        self::assertEquals(1, $discount->getQuantity());
        self::assertEquals(
            [new CalculatedTax(0.32, 19, 2.0)],
            array_values($discount->getPrice()->getCalculatedTaxes()->getElements())
        );
    }

    private function createRule(array $rules, string $name = 'Test rule', int $priority = 1): string
    {
        $id = Uuid::uuid4()->getHex();

        self::$ruleRepository->upsert(
            [
                [
                    'id' => $id,
                    'name' => $name,
                    'priority' => $priority,
                    'payload' => new AndRule($rules),
                ],
            ],
            Context::createDefaultContext(Defaults::TENANT_ID)
        );

        return $id;
    }

    private function createDiscountSurcharge(string $ruleId, string $type, float $amount, ?Rule $rule = null): string
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => sprintf('Test modifier (%s)', $type),
            'ruleId' => $ruleId,
            'type' => $type,
            'amount' => $amount,
            'filterRule' => $rule ?? new AndRule(),
        ];

        self::$discountSurchargeRepository->upsert([array_filter($data)], Context::createDefaultContext(Defaults::TENANT_ID));

        return $id;
    }

    private function createProduct(string $name, float $netPrice, float $grossPrice, float $taxRate): string
    {
        $id = Uuid::uuid4()->getHex();

        self::$productRepository->upsert([
            [
                'id' => $id,
                'name' => $name,
                'manufacturer' => ['name' => 'test'],
                'price' => ['gross' => $grossPrice, 'net' => $netPrice],
                'tax' => ['name' => 'test', 'taxRate' => $taxRate],
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        return $id;
    }
}
