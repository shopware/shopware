<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait PromotionTestFixtureBehaviour
{
    /**
     * Creates a new product in the database.
     */
    public function createTestFixtureProduct(string $productId, float $grossPrice, float $taxRate, ContainerInterface $container)
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $container->get('product.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productRepository->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => $productId,
                    'stock' => 1,
                    'name' => 'Test',
                    'active' => true,
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => $grossPrice,
                            'net' => 9, 'linked' => false,
                        ],
                    ],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => $taxRate, 'name' => 'with id'],
                    'visibilities' => [
                        ['salesChannelId' => $context->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                    ],
                    'categories' => [
                        ['id' => Uuid::randomHex(), 'name' => 'Clothing'],
                    ],
                ],
            ],
            $context->getContext()
        );
    }

    /**
     * Creates a new absolute promotion in the database.
     */
    public function createTestFixtureAbsolutePromotion(string $promotionId, string $code, float $value, ContainerInterface $container)
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $discountId = $this->createPromotion(
            $promotionId,
            $code,
            PromotionDiscountEntity::TYPE_ABSOLUTE,
            $value,
            null,
            $promotionRepository,
            $context
        );

        return $discountId;
    }

    /**
     * Creates a new percentage promotion in the database.
     */
    public function createTestFixturePercentagePromotion(string $promotionId, string $code, float $percentage, ?float $maxValue, ContainerInterface $container)
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $discountId = $this->createPromotion(
            $promotionId,
            $code,
            PromotionDiscountEntity::TYPE_PERCENTAGE,
            $percentage,
            $maxValue,
            $promotionRepository,
            $context
        );

        return $discountId;
    }

    /**
     * Creates a new percentage promotion in the database.
     */
    public function createTestFixtureFixedPricePromotion(string $promotionId, string $code, float $fixedPrice, ContainerInterface $container)
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->createPromotion(
            $promotionId,
            $code,
            PromotionDiscountEntity::TYPE_FIXED,
            $fixedPrice,
            null,
            $promotionRepository,
            $context
        );
    }

    /**
     * Creates a new advanced currency price for the provided discount
     */
    public function createTestFixtureAdvancedPrice(string $discountId, string $currency, float $price, ContainerInterface $container)
    {
        /** @var EntityRepositoryInterface $pricesRepository */
        $pricesRepository = $container->get('promotion_discount_prices.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $pricesRepository->create(
            [
                [
                    'discountId' => $discountId,
                    'currencyId' => $currency,
                    'price' => $price,
                ],
            ],
            $context->getContext()
        );
    }

    private function createPromotion(string $promotionId, string $code, string $discountType, float $value, ?float $maxValue, EntityRepositoryInterface $promotionRepository, SalesChannelContext $context)
    {
        $discountId = Uuid::randomHex();

        $promotionRepository->create(
            [
                [
                    'id' => $promotionId,
                    'name' => 'Black Friday',
                    'active' => true,
                    'code' => $code,
                    'useCodes' => true,
                    'salesChannels' => [
                        ['salesChannelId' => Defaults::SALES_CHANNEL, 'priority' => 1],
                    ],
                    'discounts' => [
                        [
                            'id' => $discountId,
                            'scope' => PromotionDiscountEntity::SCOPE_CART,
                            'type' => $discountType,
                            'value' => $value,
                            'maxValue' => $maxValue,
                            'considerAdvancedRules' => false,
                        ],
                    ],
                ],
            ],
            $context->getContext()
        );

        return $discountId;
    }
}
