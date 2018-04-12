<?php declare(strict_types=1);
use Shopware\Api\Context\Repository\ContextCartModifierRepository;
use Shopware\Api\Context\Repository\ContextRuleRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Cart\Cart\CircularCartCalculation;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\Test\Common\Generator;
use Shopware\CartBridge\Modifier\ContextCartModifierProcessor;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\Context\Rule\CalculatedCart\GoodsPriceRule;
use Shopware\Context\Rule\CalculatedCart\OrderAmountRule;
use Shopware\Context\Rule\CalculatedLineItem\IdRule;
use Shopware\Context\Rule\CalculatedLineItem\ItemTypeRule;
use Shopware\Context\Rule\CalculatedLineItem\ManufacturerRule;
use Shopware\Context\Rule\CalculatedLineItem\QuantityRule;
use Shopware\Context\Rule\CalculatedLineItem\TotalPriceRule;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\Rule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\StorefrontContext;
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
     * @var ProductRepository
     */
    public static $productRepository;

    /**
     * @var ShopContext
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
        self::$context = ShopContext::createDefaultContext();
        self::$calculation = self::$kernel->getContainer()->get(CircularCartCalculation::class);
    }

    public function testAbsoluteSurcharge()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);

        $rules = [
            new GoodsPriceRule(60, OrderAmountRule::OPERATOR_LTE),
            new GoodsPriceRule(25, OrderAmountRule::OPERATOR_GTE),
        ];
        $contextRuleId = $this->createContextRule($rules, 'Test rule');

        $modifierId = $this->createContextCartModifier(
            'Test modifier (absolute)',
            $contextRuleId,
            null,
            ContextCartModifierProcessor::ABSOLUTE_MODIFIER,
            2
        );

        $lineItems = new LineItemCollection([
            new LineItem(
                $productA,
                ProductProcessor::TYPE_PRODUCT,
                3,
                ['id' => $productA]
            ),
            new LineItem(
                $productB,
                ProductProcessor::TYPE_PRODUCT,
                1,
                ['id' => $productB]
            ),
        ]);

        $cart = new Cart('test', 'test', $lineItems, new ErrorCollection());
        $context = $this->createContext($contextRuleId);
        $calculatedCart = self::$calculation->calculate($cart, $context);

        $this->removeContextCartModifier($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->assertEquals(52, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(2, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());
    }

    public function testPercentalSurcharge()
    {
        $productA = $this->createProduct('Product A', 10, 11.9, 19);
        $productB = $this->createProduct('Product B', 20, 23.8, 19);
        $manufacturerA = $this->getManufacturersOfProduct($productA);

        $rules = [
            new GoodsPriceRule(45, OrderAmountRule::OPERATOR_LTE),
            new GoodsPriceRule(25, OrderAmountRule::OPERATOR_GTE),
        ];
        $contextRuleId = $this->createContextRule($rules, 'Test rule');

        $rules = [
            new QuantityRule(3, Rule::OPERATOR_EQ),
            new ItemTypeRule(ProductProcessor::TYPE_PRODUCT),
            new TotalPriceRule(30, Rule::OPERATOR_EQ),
            new IdRule($productA),
            new ManufacturerRule($manufacturerA),
        ];
        $modifierId = $this->createContextCartModifier(
            'Test modifier (percental)',
            $contextRuleId,
            new AndRule($rules),
            ContextCartModifierProcessor::PERCENTAL_MODIFIER,
            -20
        );

        $lineItems = new LineItemCollection([
            new LineItem(
                $productA,
                ProductProcessor::TYPE_PRODUCT,
                3,
                ['id' => $productA]
            ),
            new LineItem(
                $productB,
                ProductProcessor::TYPE_PRODUCT,
                1,
                ['id' => $productB]
            ),
        ]);

        $cart = new Cart('test', 'test', $lineItems, new ErrorCollection());

        $context = $this->createContext($contextRuleId);
        $calculatedCart = self::$calculation->calculate($cart, $context);
        $this->removeContextCartModifier($modifierId);

        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->assertEquals(46, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(-4, $lineItem->getPrice()->getTotalPrice());
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals(ContextCartModifierProcessor::TYPE, $lineItem->getType());
    }

    private function createContextRule(array $rules, string $name, int $priority = 1): string
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => $name,
            'priority' => $priority,
            'payload' => new AndRule($rules),
        ];
        self::$contextRuleRepository->upsert([$data], ShopContext::createDefaultContext());

        return $id;
    }

    private function createContextCartModifier(
        string $name,
        string $contextRuleId,
        ?Rule $rule,
        string $type,
        float $amount
    ): string {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => $name,
            'contextRuleId' => $contextRuleId,
            'type' => $type,
            'amount' => $amount,
            'rule' => $rule ?? new AndRule(),
        ];
        $data = array_filter($data);

        self::$contextCartModifierRepository->upsert([$data], ShopContext::createDefaultContext());

        return $id;
    }

    private function removeContextCartModifier(string $id)
    {
        self::$contextCartModifierRepository->delete([['id' => $id]], self::$context);
    }

    private function createProduct(
        string $name,
        float $grossPrice,
        float $netPrice,
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

    private function createContext(string $contextRuleId): StorefrontContext
    {
        $context = Generator::createContext();
        $context->setContextRulesIds([$contextRuleId]);

        return $context;
    }
}
