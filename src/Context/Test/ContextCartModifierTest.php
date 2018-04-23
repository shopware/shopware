<?php


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
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\GoodsPriceRule;
use Shopware\Context\Rule\LineItemInCartRule;
use Shopware\Context\Rule\OrderAmountRule;
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


    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

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
            new OrderAmountRule(500, OrderAmountRule::OPERATOR_LTE),
            new OrderAmountRule(25, OrderAmountRule::OPERATOR_GTE),
        ];
        $contextRuleId = $this->createContextRule($rules, 'Test rule');

        $modifierId = $this->createContextCartModifier(
            'Test modifier',
            $contextRuleId,
            null,
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

        $rules = [
            new OrderAmountRule(500, OrderAmountRule::OPERATOR_LTE),
            new OrderAmountRule(25, OrderAmountRule::OPERATOR_GTE),
        ];
        $contextRuleId = $this->createContextRule($rules, 'Test rule');

        $modifierId = $this->createContextCartModifier(
            'Test modifier',
            $contextRuleId,
            null,
            null,
            10
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
        $lineItem = $calculatedCart->getCalculatedLineItems()->get($modifierId);

        $this->assertEquals(55, $calculatedCart->getPrice()->getTotalPrice());
        $this->assertInstanceOf(CalculatedLineItem::class, $lineItem);
        $this->assertEquals(5, $lineItem->getPrice()->getTotalPrice());
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
        float $absolute = null,
        float $percental = null
    ): string {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => $name,
            'contextRuleId' => $contextRuleId,
            'absolute' => $absolute,
            'percental' => $percental,
            'rule' => $rule
        ];

        $data = array_filter($data);

        self::$contextCartModifierRepository->upsert([$data], ShopContext::createDefaultContext());

        return $id;
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

    private function createContext(string $contextRuleId): StorefrontContext
    {
        $context = Generator::createContext(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            [$contextRuleId]
        );
        return $context;
    }
}