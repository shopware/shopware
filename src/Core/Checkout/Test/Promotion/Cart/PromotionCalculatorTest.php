<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Promotion\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

class PromotionCalculatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PromotionCalculator $promotionCalculator;

    private SalesChannelContext $salesChannelContext;

    private EntityRepositoryInterface $promotionRepository;

    protected function setUp(): void
    {
        $container = $this->getContainer();
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
        $original = new Cart('original', Uuid::randomHex());
        $toCalculate = new Cart('toCalculate', Uuid::randomHex());

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertEmpty($toCalculate->getLineItems());
    }

    public function testCalculateDoesNotAddDiscountItemsWithDeliveryScope(): void
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItem->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_DELIVERY);
        $discountItem->setPayloadValue('exclusions', []);
        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart('original', Uuid::randomHex());
        $toCalculate = new Cart('toCalculate', Uuid::randomHex());

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertEmpty($toCalculate->getLineItems());
    }

    public function testCalculateAddsValidPromotionToCalculatedCart(): void
    {
        $promotionId = $this->getPromotionId();
        $discountItem = $this->getDiscountItem($promotionId);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart('original', Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart('toCalculate', Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);
        static::assertSame(-10.0, $promotionLineItems->first()->getPrice()->getTotalPrice());
    }

    public function testCalculateWithPreventedCombination(): void
    {
        $nonePreventedPromotionId = $this->getPromotionId();
        $discountItemToBeExcluded = $this->getDiscountItem($nonePreventedPromotionId);

        $preventedPromotionId = $this->getPromotionId(true);
        $validDiscountItem = $this->getDiscountItem($preventedPromotionId);
        $validDiscountItem->setPayloadValue('preventCombination', true);

        $discountItems = new LineItemCollection([$discountItemToBeExcluded, $validDiscountItem]);

        $original = new Cart('original', Uuid::randomHex());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $productLineItem->setStackable(true);

        $toCalculate = new Cart('toCalculate', Uuid::randomHex());
        $toCalculate->add($productLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        // We expect the product plus 1 promotion in the cart so overall a count of 2 items
        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->salesChannelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        // Make sure that only the expected promotion is in the cart
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertSame(-10.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $validDiscountItem->getReferencedId());
    }

    private function getPromotionId(bool $preventCombination = false): string
    {
        $promotionId = Uuid::randomHex();

        $this->promotionRepository->create(
            [
                [
                    'id' => $promotionId,
                    'active' => true,
                    'exclusive' => false,
                    'code' => \sprintf('phpUnit-%s', $promotionId),
                    'useCodes' => true,
                    'useIndividualCodes' => false,
                    'useSetGroups' => false,
                    'name' => 'PHP Unit promotion',
                    'preventCombination' => $preventCombination,
                    'discounts' => [
                        [
                            'scope' => PromotionDiscountEntity::SCOPE_CART,
                            'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                            'value' => 10.0,
                            'considerAdvancedRules' => false,
                        ],
                    ],
                ],
            ],
            $this->salesChannelContext->getContext()
        );

        return $promotionId;
    }

    private function getDiscountItem(string $promotionId): LineItem
    {
        $discountItemToBeExcluded = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItemToBeExcluded->setRequirement(null);
        $discountItemToBeExcluded->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $discountItemToBeExcluded->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discountItemToBeExcluded->setPayloadValue('exclusions', []);
        $discountItemToBeExcluded->setPayloadValue('promotionId', $promotionId);
        $discountItemToBeExcluded->setReferencedId($promotionId);
        $discountItemToBeExcluded->setLabel('PHPUnit');
        $discountItemToBeExcluded->setPriceDefinition(new AbsolutePriceDefinition(-10.0));

        return $discountItemToBeExcluded;
    }
}
