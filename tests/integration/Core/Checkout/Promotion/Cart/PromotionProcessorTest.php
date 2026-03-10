<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Promotion\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\TestShortHands;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->promotionRepository = static::getContainer()->get(\sprintf('%s.repository', PromotionDefinition::ENTITY_NAME));
    }

    public function testPromotionDiscountWithUninitializedFilterKeys(): void
    {
        $taxId = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM tax LIMIT 1');

        $product = (new ProductBuilder($this->ids, 'test-product'))
            ->price(100)
            ->stock(10)
            ->visibility();

        $productData = $product->build();
        $productData['taxId'] = $taxId;

        static::getContainer()->get('product.repository')
            ->create([$productData], Context::createDefaultContext());

        $context = $this->getContext();
        $promotionId = $this->createPromotionWithAdvancedRulesButUninitializedKeys($context);
        $cart = $this->addProductToCart($product->id, $context);

        $criteria = new Criteria([$promotionId]);
        $criteria->addAssociation('discounts');

        $promotion = $this->promotionRepository->search($criteria, $context->getContext())->first();
        static::assertInstanceOf(PromotionEntity::class, $promotion);

        $discounts = $promotion->getDiscounts();
        static::assertNotNull($discounts, 'Promotion should have discounts');
        $discount = $discounts->first();
        static::assertInstanceOf(PromotionDiscountEntity::class, $discount);

        // @deprecated tag:v6.8.0 - Empty sorter and applier keys will no longer be supported.
        static::assertSame('', $discount->getSorterKey());
        static::assertSame('', $discount->getApplierKey());

        $promotionItems = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertGreaterThan(0, $promotionItems->count(), 'Promotion should be applied to cart');
        static::assertSame(95.0, $cart->getPrice()->getTotalPrice(), 'Cart total should be 95€ (100€ - 5€ discount)');

        $errors = $cart->getErrors()->getElements();
        static::assertCount(1, $errors, 'Cart should have exactly one error informational entry');
        $promotionError = array_values($errors)[0];
        static::assertInstanceOf(PromotionCartAddedInformationError::class, $promotionError);
        static::assertStringContainsString('has been added', $promotionError->getMessage());
    }

    /**
     * Creates a promotion with considerAdvancedRules=true but leaves filter keys uninitialized.
     * This simulates the Admin UI scenario where "Apply to specific range" is enabled
     * but the "Apply to" and "Sort by" dropdowns are left as "Select...".
     */
    private function createPromotionWithAdvancedRulesButUninitializedKeys(SalesChannelContext $context): string
    {
        $promotionId = Uuid::randomHex();
        $validFrom = new \DateTime();
        $validFrom->sub(new \DateInterval('PT1H'));
        $validUntil = new \DateTime();
        $validUntil->add(new \DateInterval('P1D'));
        $promotionData = [
            'id' => $promotionId,
            'active' => true,
            'exclusive' => false,
            'priority' => 1,
            'useCodes' => false,
            'useIndividualCodes' => false,
            'useSetGroups' => false,
            'name' => 'Test Auto Promotion with Advanced Rules',
            'preventCombination' => false,
            'validFrom' => $validFrom,
            'validUntil' => $validUntil,
            'maxRedemptionsGlobal' => null,
            'maxRedemptionsPerCustomer' => null,
            'salesChannels' => [
                [
                    'salesChannelId' => $context->getSalesChannel()->getId(),
                    'priority' => 1,
                ],
            ],
            'discounts' => [
                [
                    'id' => Uuid::randomHex(),
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                    'value' => 5.0,
                    'considerAdvancedRules' => true,
                    'usageKey' => 'cart-usage',
                    'promotionDiscountPrices' => [],
                ],
            ],
        ];

        $this->promotionRepository->create(
            [$promotionData],
            $context->getContext()
        );

        return $promotionId;
    }
}
