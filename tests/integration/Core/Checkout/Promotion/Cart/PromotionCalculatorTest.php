<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionCalculatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use LineItemTestFixtureBehaviour;
    use PromotionTestFixtureBehaviour;

    private PromotionCalculator $promotionCalculator;

    private SalesChannelContext $salesChannelContext;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->promotionCalculator = $container->get(PromotionCalculator::class);
        $this->promotionRepository = $container->get(\sprintf('%s.repository', PromotionDefinition::ENTITY_NAME));

        $salesChannelService = $container->get(SalesChannelContextService::class);
        $this->salesChannelContext = $salesChannelService->get(
            new SalesChannelContextServiceParameters(
                TestDefaults::SALES_CHANNEL,
                Uuid::randomHex()
            )
        );
    }

    public function testCalculateDoesNotAddDiscountItemsWithoutScope(): void
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());
        $toCalculate = new Cart(Uuid::randomHex());

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertEmpty($toCalculate->getLineItems());
    }

    public function testCalculateDoesNotAddDiscountItemsWithDeliveryScope(): void
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItem->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_DELIVERY);
        $discountItem->setPayloadValue('exclusions', []);
        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());
        $toCalculate = new Cart(Uuid::randomHex());

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertEmpty($toCalculate->getLineItems());
    }

    public function testCalculateAddsValidPromotionToCalculatedCart(): void
    {
        $promotionId = $this->getPromotionId();
        $discountItem = $this->getDiscountItem($promotionId);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionLineItem = $promotionLineItems->first();

        static::assertNotNull($promotionLineItem);
        static::assertNotNull($promotionLineItem->getPrice());

        static::assertSame(-10.0, $promotionLineItem->getPrice()->getTotalPrice());
    }

    public function testCalculateWithPreventedCombination(): void
    {
        $nonePreventedPromotionId = $this->getPromotionId();
        $discountItemToBeExcluded = $this->getDiscountItem($nonePreventedPromotionId);

        $preventedPromotionId = $this->getPromotionId(true);
        $validDiscountItem = $this->getDiscountItem($preventedPromotionId);
        $validDiscountItem->setPayloadValue('preventCombination', true);

        $discountItems = new LineItemCollection([$discountItemToBeExcluded, $validDiscountItem]);

        $original = new Cart(Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        // We expect the product plus 1 promotion in the cart so overall a count of 2 items
        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        // Make sure that only the expected promotion is in the cart
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertNotNull($promotionItem);
        static::assertNotNull($promotionItem->getPrice());
        static::assertSame(-10.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $validDiscountItem->getReferencedId());
    }

    public function testAutomaticExclusionsDontAddError(): void
    {
        $firstPromotionId = $this->getPromotionId(true, 1, false);
        $firstDiscountItem = $this->getDiscountItem($firstPromotionId);
        $firstDiscountItem->setPriceDefinition(new AbsolutePriceDefinition(-20.0));
        $firstDiscountItem->setPayloadValue('preventCombination', true);
        $firstDiscountItem->setPayloadValue('priority', 1);

        $secondPromotionId = $this->getPromotionId(true, 2, false);
        $secondDiscountItem = $this->getDiscountItem($secondPromotionId);
        $secondDiscountItem->setPayloadValue('preventCombination', true);
        $secondDiscountItem->setPayloadValue('priority', 2);

        $discountItems = new LineItemCollection([$firstDiscountItem, $secondDiscountItem]);

        $original = new Cart(Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        // We expect the product plus 1 promotion in the cart so overall a count of 2 items
        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        // Make sure that only the expected promotion is in the cart
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertNotNull($promotionItem);
        static::assertNotNull($promotionItem->getPrice());
        static::assertSame(-10.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $secondDiscountItem->getReferencedId());

        // Switch priorities and make sure that the other promotion is now in the cart
        $firstDiscountItem->setPayloadValue('priority', 2);
        $secondDiscountItem->setPayloadValue('priority', 1);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertNotNull($promotionItem);
        static::assertNotNull($promotionItem->getPrice());
        static::assertSame(-20.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $firstDiscountItem->getReferencedId());

        $cartErrors = $toCalculate->getErrors();
        foreach ($cartErrors as $cartError) {
            static::assertNotInstanceOf(PromotionExcludedError::class, $cartError);
        }
    }

    public function testFixedUnitPricePromotionisNotEligible(): void
    {
        $promotionId = $this->getPromotionId(type: PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $discountItem = $this->getDiscountItem($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(8, 8, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(8, 8, 8, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());

        static::assertNotNull($toCalculate->getErrors()->first());
        static::assertSame('Promotion PHPUnit not eligible for cart!', $toCalculate->getErrors()->first()->getMessage());
    }

    public function testFixedUnitPricePromotions(): void
    {
        $promotionId = $this->getPromotionId(type: PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $discountItem = $this->getDiscountItem($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $discountItem->setPayloadValue('filter', ['considerAdvancedRules' => true, 'applierKey' => 'ALL']);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(20, 20, 20, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());

        static::assertNotNull($toCalculate->getErrors()->first());
        static::assertSame('Discount PHPUnit has been added', $toCalculate->getErrors()->first()->getMessage());
        static::assertSame(10.0, $toCalculate->getPrice()->getTotalPrice());
        static::assertCount(2, $toCalculate->getLineItems());
    }

    public function testTest(): void
    {
        $promotionId = Uuid::randomHex();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        /** @var AbstractSalesChannelContextFactory $factory */
        $factory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $factory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $data = [
            'id' => $promotionId,
            'code' => 'Black Friday',
            'useCodes' => true,
            'useSetGroups' => true,
        ];

        $this->createPromotionWithCustomData($data, $this->promotionRepository, $context);
        $this->createSetGroupDiscount($promotionId, 1, static::getContainer(), 50, null, applierKey: '2');
        $this->createSetGroupFixture('COUNT', 2, 'PRICE_ASC', $promotionId, static::getContainer());

        $promotion = new LineItem($promotionId, PromotionProcessor::LINE_ITEM_TYPE, 'Black Friday');

        $lineItem = new LineItem($product1, LineItem::PRODUCT_LINE_ITEM_TYPE, $product1);
        $lineItem->setRemovable(true);

        $lineItem2 = new LineItem($product2, LineItem::PRODUCT_LINE_ITEM_TYPE, $product2);
        $lineItem2->setRemovable(true);

        $lineItem3 = new LineItem($product3, LineItem::PRODUCT_LINE_ITEM_TYPE, $product2);
        $lineItem3->setRemovable(true);

        /** @var CartService $cartService */
        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->createNew('test-token');

        $firstCalculatedCard = $cartService->add($cart, [$lineItem, $lineItem2, $lineItem3, $promotion], $context);
        static::assertNotNull($firstCalculatedCard->getErrors()->first());
        static::assertSame('Discount Black Friday has been added', $firstCalculatedCard->getErrors()->first()->getMessage());
        static::assertCount(4, $firstCalculatedCard->getLineItems());
        static::assertSame(25.0, $firstCalculatedCard->getPrice()->getTotalPrice());

        $firstCalculatedCard->setErrors(new ErrorCollection());

        $secondCalculatedCard = $cartService->remove($firstCalculatedCard, $lineItem->getId(), $context);
        static::assertCount(0, $secondCalculatedCard->getErrors());
        static::assertCount(3, $secondCalculatedCard->getLineItems());
        static::assertSame(15.0, $secondCalculatedCard->getPrice()->getTotalPrice());

        $thirdCalculatedCard = $cartService->remove($secondCalculatedCard, $lineItem2->getId(), $context);
        static::assertNotNull($thirdCalculatedCard->getErrors()->first());
        static::assertSame('Promotion Black Friday not eligible for cart!', $thirdCalculatedCard->getErrors()->first()->getMessage());
        static::assertCount(1, $thirdCalculatedCard->getLineItems());
        static::assertSame(10.0, $thirdCalculatedCard->getPrice()->getTotalPrice());
    }

    public function testCustomLineItemNotStackableBecomesStackable(): void
    {
        $promotionId = $this->getPromotionId();
        $discountItem = $this->getDiscountItem($promotionId);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(90.0, 90.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $customLineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);
        $customLineItem->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $customLineItem->setStackable(false);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->add($customLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(3, $toCalculate->getLineItems());
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionLineItem = $promotionLineItems->first();

        static::assertNotNull($promotionLineItem);
        static::assertNotNull($promotionLineItem->getPrice());

        static::assertSame(-10.0, $promotionLineItem->getPrice()->getTotalPrice());

        // Ensure that non-stackable items are still not stackable!
        $customLineItem = $toCalculate->get($customLineItem->getId());
        static::assertNotNull($customLineItem);
        static::assertFalse($customLineItem->isStackable());
    }

    private function getPromotionId(bool $preventCombination = false, int $priority = 1, bool $useCodes = true, string $type = PromotionDiscountEntity::TYPE_ABSOLUTE): string
    {
        $promotionId = Uuid::randomHex();

        $promotionData = [
            'id' => $promotionId,
            'active' => true,
            'exclusive' => false,
            'priority' => $priority,
            'code' => \sprintf('phpUnit-%s', $promotionId),
            'useCodes' => $useCodes,
            'useIndividualCodes' => false,
            'useSetGroups' => false,
            'name' => 'PHP Unit promotion',
            'preventCombination' => $preventCombination,
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => $type,
                    'value' => 10.0,
                    'considerAdvancedRules' => false,
                ],
            ],
        ];

        if (!$useCodes) {
            unset($promotionData['code']);
        }

        $this->promotionRepository->create(
            [
                $promotionData,
            ],
            $this->salesChannelContext->getContext()
        );

        return $promotionId;
    }

    private function getDiscountItem(string $promotionId, string $typen = PromotionDiscountEntity::TYPE_ABSOLUTE): LineItem
    {
        $discountItemToBeExcluded = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItemToBeExcluded->setRequirement(null);
        $discountItemToBeExcluded->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $discountItemToBeExcluded->setPayloadValue('discountType', $typen);
        $discountItemToBeExcluded->setPayloadValue('exclusions', []);
        $discountItemToBeExcluded->setPayloadValue('promotionId', $promotionId);
        $discountItemToBeExcluded->setReferencedId($promotionId);
        $discountItemToBeExcluded->setLabel('PHPUnit');
        $discountItemToBeExcluded->setPriceDefinition(new AbsolutePriceDefinition(-10.0));

        return $discountItemToBeExcluded;
    }

    private function createProduct(): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('product.repository')
            ->create([
                [
                    'id' => $id,
                    'name' => 'test',
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 7, 'linked' => false],
                    ],
                    'purchasePrices' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                        ['currencyId' => Uuid::randomHex(), 'gross' => 150, 'net' => 100, 'linked' => false],
                    ],
                    'active' => true,
                    'taxId' => $this->getValidTaxId(),
                    'weight' => 100,
                    'height' => 101,
                    'width' => 102,
                    'length' => 103,
                    'visibilities' => [
                        ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                    ],
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => 'test',
                        ],
                    ],
                ],
            ], Context::createDefaultContext());

        return $id;
    }
}
