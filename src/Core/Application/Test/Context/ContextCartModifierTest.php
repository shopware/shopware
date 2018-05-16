<?php declare(strict_types=1);

namespace Shopware\Application\Test\Context;

use Shopware\Application\Context\Repository\ContextCartModifierRepository;
use Shopware\Application\Context\Repository\ContextRuleRepository;
use Shopware\Content\Product\ProductRepository;
use Shopware\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\Error\ErrorCollection;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\LineItem\LineItem;
use Shopware\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Checkout\Test\Cart\Common\Generator;
use Shopware\Application\Context\Cart\ContextCartModifierProcessor;
use Shopware\Content\Product\Cart\ProductProcessor;
use Shopware\Application\Context\Rule\CalculatedCart\GoodsCountRule;
use Shopware\Application\Context\Rule\CalculatedCart\GoodsPriceRule;
use Shopware\Application\Context\Rule\CalculatedLineItem\LineItemOfTypeRule;
use Shopware\Application\Context\Rule\CalculatedLineItem\LineItemTotalPriceRule;
use Shopware\Application\Context\Rule\CalculatedLineItem\LineItemWithQuantityRule;
use Shopware\Application\Context\Rule\CalculatedLineItem\ProductOfManufacturerRule;
use Shopware\Application\Context\Rule\Container\AndRule;
use Shopware\Application\Context\Rule\Rule;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContextCartModifierTest extends KernelTestCase
{
    /**
     * @var ContextCartModifierRepository
     */
    public static $contextCartModifierRepository;

    /**
     * @var ContextRuleRepository
     */
    public static $contextRuleRepository;

    /**
     * @var \Shopware\Content\Product\ProductRepository
     */
    public static $productRepository;

    /**
     * @var ApplicationContext
     */
    public static $context;

    /**
     * @var CircularCartCalculation
     */
    public static $calculation;

    public function setUp()
    {
        self::bootKernel();
        self::$contextCartModifierRepository = self::$kernel->getContainer()->get(ContextCartModifierRepository::class);
        self::$contextRuleRepository = self::$kernel->getContainer()->get(ContextRuleRepository::class);
        self::$productRepository = self::$kernel->getContainer()->get(ProductRepository::class);
        self::$context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);
        self::$calculation = self::$kernel->getContainer()->get(CircularCartCalculation::class);
    }

    public function testAbsoluteSurchargeMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);

        $contextRuleId = $this->createContextRule([
            new GoodsPriceRule(25, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::ABSOLUTE_MODIFIER,
            2
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removeContextCartModifier($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(61.5, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertEquals(51.68, $calculatedCart->getPrice()->getNetPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(2, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());

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

        $contextRuleId = $this->createContextRule([
            new GoodsPriceRule(100, Rule::OPERATOR_GTE),
            new GoodsPriceRule(200, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::ABSOLUTE_MODIFIER,
            2
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removeContextCartModifier($modifierId);

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

        $contextRuleId = $this->createContextRule([
            new GoodsPriceRule(10, Rule::OPERATOR_GTE),
            new GoodsPriceRule(100, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::ABSOLUTE_MODIFIER,
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

        $this->removeContextCartModifier($modifierId);

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
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());

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

        $contextRuleId = $this->createContextRule([
            new GoodsPriceRule(25, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $rules = [
            new LineItemWithQuantityRule($productA, 3),
            new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT),
            new LineItemTotalPriceRule(35.7, Rule::OPERATOR_EQ),
            new ProductOfManufacturerRule($manufacturerA),
        ];

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::PERCENTAL_MODIFIER,
            -20,
            new AndRule($rules)
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removeContextCartModifier($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(52.36, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-7.14, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());

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
        $contextRuleId = $this->createContextRule($rules, 'Test rule');

        $rules = [
            new LineItemWithQuantityRule($productA, 3),
            new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT),
            new LineItemTotalPriceRule(30, Rule::OPERATOR_EQ),
            new ProductOfManufacturerRule($manufacturerA),
        ];

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::PERCENTAL_MODIFIER,
            -20,
            new AndRule($rules)
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );
        $this->removeContextCartModifier($modifierId);

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

        $contextRuleId = $this->createContextRule([
            new GoodsPriceRule(25, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $rules = [
            new LineItemWithQuantityRule($productA, 3),
            new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT),
            new LineItemTotalPriceRule(35.7, Rule::OPERATOR_EQ),
            new ProductOfManufacturerRule($manufacturerA),
        ];

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::PERCENTAL_MODIFIER,
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

        $this->removeContextCartModifier($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(52.36, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-7.14, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());
        $this->assertEmpty($lineItem->getPrice()->getCalculatedTaxes()->getElements());
    }

    public function testPercentalDiscountWithDifferentTaxRatesMatch()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 7);

        $contextRuleId = $this->createContextRule([
            new GoodsPriceRule(5, Rule::OPERATOR_GTE),
            new GoodsPriceRule(60, Rule::OPERATOR_LTE),
        ]);

        $modifierId = $this->createContextCartModifier(
            $contextRuleId,
            ContextCartModifierProcessor::PERCENTAL_MODIFIER,
            -20
        );

        $calculatedCart = $this->calculate(
            [
                [$productA, 3],
                [$productB, 1],
            ]
        );

        $this->removeContextCartModifier($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->markTestIncomplete('Should work after Ticket NEXT-286 is done.');

        $this->assertEquals(47.6, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-11.9, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());

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

    private function createContextRule(array $rules, string $name = 'Test rule', int $priority = 1): string
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => $name,
            'priority' => $priority,
            'payload' => new AndRule($rules),
        ];
        $context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);
        self::$contextRuleRepository->upsert([$data], $context);

        return $id;
    }

    private function createContextCartModifier(
        string $contextRuleId,
        string $type,
        float $amount,
        ?Rule $rule = null
    ): string {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => sprintf('Test modifier (%s)', $type),
            'contextRuleId' => $contextRuleId,
            'type' => $type,
            'amount' => $amount,
            'rule' => $rule ?? new AndRule(),
        ];
        $data = array_filter($data);

        self::$contextCartModifierRepository->upsert([$data], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        return $id;
    }

    private function removeContextCartModifier(string $id)
    {
        self::$contextCartModifierRepository->delete([['id' => $id]], self::$context);
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
    ): StorefrontContext {
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
