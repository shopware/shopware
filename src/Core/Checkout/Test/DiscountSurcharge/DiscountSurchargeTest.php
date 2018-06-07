<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge;

use Shopware\Core\Checkout\DiscountSurcharge\Cart\DiscountSurchargeProcessor;
use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Content\Rule\RuleRepository;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Checkout\Cart\Rule\ProductOfManufacturerRule;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Cart\ProductProcessor;
use Shopware\Core\Content\Product\ProductRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DiscountSurchargeTest extends KernelTestCase
{
    /**
     * @var \Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeRepository
     */
    public static $discountSurchargeRepository;

    /**
     * @var \Shopware\Core\Content\Rule\RuleRepository
     */
    public static $ruleRepository;

    /**
     * @var \Shopware\Core\Content\Product\ProductRepository
     */
    public static $productRepository;

    /**
     * @var \Shopware\Core\Framework\Context
     */
    public static $context;

    /**
     * @var CircularCartCalculation
     */
    public static $calculation;

    public function setUp()
    {
        parent::setUp();

        self::bootKernel();
        self::$ruleRepository = self::$container->get(RuleRepository::class);
        self::$productRepository = self::$container->get(ProductRepository::class);
        self::$discountSurchargeRepository = self::$kernel->getContainer()->get(DiscountSurchargeRepository::class);
        self::$context = Context::createDefaultContext(Defaults::TENANT_ID);
        self::$calculation = self::$container->get(CircularCartCalculation::class);
    }

    public function testAbsoluteSurchargeMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);

        $ruleId = $this->createRule([
            new GoodsPriceRule(25, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $id = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::ABSOLUTE_MODIFIER,
            2
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removediscountSurcharge($id);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($id);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(61.5, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertEquals(51.68, $calculatedCart->getPrice()->getNetPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(2, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(DiscountSurchargeProcessor::TYPE, $lineItem->getType());

        $this->assertArrayHasKey(19, $calculatedCart->getPrice()->getCalculatedTaxes());
        $tax = $calculatedCart->getPrice()->getCalculatedTaxes()->get(19);
        $this->assertEquals(9.82, $tax->getTax());
        $this->assertEquals(61.5, $tax->getPrice());
        $this->assertEquals(19, $tax->getTaxRate());

        $this->assertArrayHasKey(19, $calculatedCart->getPrice()->getTaxRules());
        /** @var PercentageTaxRule $taxRule */
        $taxRule = $calculatedCart->getPrice()->getTaxRules()->get(19);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(19, $taxRule->getRate());
        $this->assertEquals(100, $taxRule->getPercentage());
    }

    public function testAbsoluteSurchargeWithNoMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);

        $ruleId = $this->createRule([
            new GoodsPriceRule(100, Rule::OPERATOR_GTE),
            new GoodsPriceRule(200, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::ABSOLUTE_MODIFIER,
            2
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removediscountSurcharge($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(59.5, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertEquals(50, $calculatedCart->getPrice()->getNetPrice());

        $this->assertArrayHasKey(19, $calculatedCart->getPrice()->getCalculatedTaxes());
        $tax = $calculatedCart->getPrice()->getCalculatedTaxes()->get(19);
        $this->assertEquals(9.5, $tax->getTax());
        $this->assertEquals(59.5, $tax->getPrice());
        $this->assertEquals(19, $tax->getTaxRate());

        $this->assertArrayHasKey(19, $calculatedCart->getPrice()->getTaxRules());
        /** @var PercentageTaxRule $taxRule */
        $taxRule = $calculatedCart->getPrice()->getTaxRules()->get(19);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(19, $taxRule->getRate());
        $this->assertEquals(100, $taxRule->getPercentage());
        $this->assertNull($lineItem);
    }

    public function testAbsoluteSurchargeWithNetPricesMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);

        $ruleId = $this->createRule([
            new GoodsPriceRule(10, Rule::OPERATOR_GTE),
            new GoodsPriceRule(100, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::ABSOLUTE_MODIFIER,
            2
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ],
            false,
            false
        );

        $this->removediscountSurcharge($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(61.88, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertEquals(52, $calculatedCart->getPrice()->getNetPrice());

        $this->assertArrayHasKey(19, $calculatedCart->getPrice()->getCalculatedTaxes());
        $tax = $calculatedCart->getPrice()->getCalculatedTaxes()->get(19);
        $this->assertEquals(9.88, $tax->getTax());
        $this->assertEquals(52, $tax->getPrice());
        $this->assertEquals(19, $tax->getTaxRate());

        $this->assertArrayHasKey(19, $calculatedCart->getPrice()->getTaxRules());
        /** @var PercentageTaxRule $taxRule */
        $taxRule = $calculatedCart->getPrice()->getTaxRules()->get(19);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(19, $taxRule->getRate());
        $this->assertEquals(100, $taxRule->getPercentage());

        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(2, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(DiscountSurchargeProcessor::TYPE, $lineItem->getType());

        $taxRule = $lineItem->getPrice()->getTaxRules()->get(19);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(19, $taxRule->getRate());
        $this->assertEquals(100, $taxRule->getPercentage());
        $this->assertCount(1, $lineItem->getPrice()->getTaxRules()->getElements());
    }

    public function testPercentalDiscountMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);
        $manufacturerA = $this->getManufacturersOfProduct($productA);

        $ruleId = $this->createRule([
            new GoodsPriceRule(25, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $rules = [
            new LineItemWithQuantityRule($productA, 3),
            new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT),
            new LineItemTotalPriceRule(35.7, Rule::OPERATOR_EQ),
            new ProductOfManufacturerRule($manufacturerA),
        ];

        $modifierId = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::PERCENTAL_MODIFIER,
            -20,
            new AndRule($rules)
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removediscountSurcharge($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(52.36, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-7.14, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(DiscountSurchargeProcessor::TYPE, $lineItem->getType());

        $this->assertArrayHasKey(19, $lineItem->getPrice()->getCalculatedTaxes());
        $tax = $lineItem->getPrice()->getCalculatedTaxes()->get(19);
        $this->assertEquals(19, $tax->getTaxRate());
        $this->assertEquals(-1.14, $tax->getTax());
        $this->assertEquals(-7.14, $tax->getPrice());
        $this->assertCount(1, $lineItem->getPrice()->getCalculatedTaxes()->getElements());

        $this->assertArrayHasKey(19, $lineItem->getPrice()->getTaxRules());
        /** @var PercentageTaxRule $taxRule */
        $taxRule = $lineItem->getPrice()->getTaxRules()->get(19);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(19, $taxRule->getRate());
        $this->assertEquals(100, $taxRule->getPercentage());
        $this->assertCount(1, $lineItem->getPrice()->getTaxRules()->getElements());
    }

    public function testPercentalDiscountWithNoMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);
        $manufacturerA = $this->getManufacturersOfProduct($productA);

        $rules = [
            new GoodsCountRule(2, Rule::OPERATOR_NEQ),
        ];
        $ruleId = $this->createRule($rules, 'Test rule');

        $rules = [
            new LineItemWithQuantityRule($productA, 3),
            new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT),
            new LineItemTotalPriceRule(30, Rule::OPERATOR_EQ),
            new ProductOfManufacturerRule($manufacturerA),
        ];

        $modifierId = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::PERCENTAL_MODIFIER,
            -20,
            new AndRule($rules)
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );
        $this->removediscountSurcharge($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(59.5, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertNull($lineItem);
    }

    public function testPercentalDiscountTaxFreeMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);
        $manufacturerA = $this->getManufacturersOfProduct($productA);

        $ruleId = $this->createRule([
            new GoodsPriceRule(25, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $rules = [
            new LineItemWithQuantityRule($productA, 3),
            new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT),
            new LineItemTotalPriceRule(35.7, Rule::OPERATOR_EQ),
            new ProductOfManufacturerRule($manufacturerA),
        ];

        $modifierId = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::PERCENTAL_MODIFIER,
            -20,
            new AndRule($rules)
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ],
            true
        );

        $this->removediscountSurcharge($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(52.36, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-7.14, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(DiscountSurchargeProcessor::TYPE, $lineItem->getType());
        $this->assertEmpty($lineItem->getPrice()->getCalculatedTaxes()->getElements());
    }

    public function testPercentalDiscountWithDifferentTaxRatesMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 7);

        $ruleId = $this->createRule([
            new GoodsPriceRule(5, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createDiscountSurcharge(
            $ruleId,
            DiscountSurchargeProcessor::PERCENTAL_MODIFIER,
            -20
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removediscountSurcharge($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(47.6, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-11.9, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(DiscountSurchargeProcessor::TYPE, $lineItem->getType());

        $this->assertCount(2, $lineItem->getPrice()->getCalculatedTaxes()->getElements());
        $this->assertCount(2, $lineItem->getPrice()->getTaxRules()->getElements());

        $this->assertArrayHasKey(19, $lineItem->getPrice()->getCalculatedTaxes());
        $tax = $lineItem->getPrice()->getCalculatedTaxes()->get(19);
        $this->assertEquals(19, $tax->getTaxRate());
        $this->assertEquals(-1.14, $tax->getTax());
        $this->assertEquals(-7.14, $tax->getPrice());

        $this->assertArrayHasKey(19, $lineItem->getPrice()->getTaxRules());
        /** @var PercentageTaxRule $taxRule */
        $taxRule = $lineItem->getPrice()->getTaxRules()->get(19);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(19, $taxRule->getRate());
        $this->assertEquals(60, $taxRule->getPercentage());

        $this->assertArrayHasKey(7, $lineItem->getPrice()->getCalculatedTaxes());
        $tax = $lineItem->getPrice()->getCalculatedTaxes()->get(7);
        $this->assertEquals(7, $tax->getTaxRate());
        $this->assertEquals(-0.31, $tax->getTax());
        $this->assertEquals(-4.76, $tax->getPrice());

        $this->assertArrayHasKey(7, $lineItem->getPrice()->getTaxRules());
        /** @var PercentageTaxRule $taxRule */
        $taxRule = $lineItem->getPrice()->getTaxRules()->get(7);
        $this->assertInstanceOf(PercentageTaxRule::class, $taxRule);
        $this->assertEquals(7, $taxRule->getRate());
        $this->assertEquals(40, $taxRule->getPercentage());
    }

    private function createRule(array $rules, string $name = 'Test rule', int $priority = 1): string
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => $name,
            'priority' => $priority,
            'payload' => new AndRule($rules),
        ];
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        self::$ruleRepository->upsert([$data], $context);

        return $id;
    }

    private function createDiscountSurcharge(
        string $ruleId,
        string $type,
        float $amount,
        ?Rule $rule = null
    ): string {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => sprintf('Test modifier (%s)', $type),
            'ruleId' => $ruleId,
            'type' => $type,
            'amount' => $amount,
            'rule' => $rule ?? new AndRule(),
        ];
        $data = array_filter($data);

        self::$discountSurchargeRepository->upsert([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        return $id;
    }

    private function removediscountSurcharge(string $id)
    {
        self::$discountSurchargeRepository->delete([['id' => $id]], self::$context);
    }

    private function createProduct(
        string $name,
        float $netPrice,
        float $grossPrice,
        float $taxRate
    ): string {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => $name,
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => $grossPrice, 'net' => $netPrice],
            'tax' => ['name' => 'test', 'rate' => $taxRate],
        ];
        self::$productRepository->upsert([$data], self::$context);

        return $id;
    }

    private function getManufacturersOfProduct(string $productId): string
    {
        $product = self::$productRepository->readBasic([$productId], self::$context)->get($productId);

        return $product->getManufacturer()->getId();
    }

    private function createContext(
        bool $taxFree,
        bool $displayGross
    ): CheckoutContext {
        $context = Generator::createContext();

        $context->getCurrentCustomerGroup()->setDisplayGross($displayGross);
        $context->getShippingLocation()->getCountry()->setTaxFree($taxFree);

        return $context;
    }

    private function calculate(
        array $products,
        bool $taxFree = false,
        bool $displayGross = true
    ): CalculatedCart {
        $lineItems = [];

        foreach ($products as $product) {
            $lineItems[] = new LineItem(
                $product[0],
                ProductProcessor::TYPE_PRODUCT,
                $product[1],
                ['id' => $product[0]]
            );
        }

        $collection = new LineItemCollection($lineItems);

        $cart = new Cart('test', 'test', $collection, new ErrorCollection());
        $context = $this->createContext($taxFree, $displayGross);

        return self::$calculation->calculate($cart, $context);
    }
}
