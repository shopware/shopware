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
    private function createTestFixtureProduct(string $productId, float $grossPrice, float $taxRate, ContainerInterface $container)
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
     *
     * @return string
     */
    private function createTestFixtureAbsolutePromotion(string $promotionId, string $code, float $value, ContainerInterface $container, string $scope = PromotionDiscountEntity::SCOPE_CART)
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );

        return $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, $scope, $value, null, $container, $context);
    }

    /**
     * Creates a new percentage promotion in the database.
     *
     * @return string
     */
    private function createTestFixturePercentagePromotion(string $promotionId, string $code, float $percentage, ?float $maxValue, ContainerInterface $container, string $scope = PromotionDiscountEntity::SCOPE_CART)
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );

        return $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, $scope, $percentage, $maxValue, $container, $context);
    }

    /**
     * Creates a new advanced currency price for the provided discount
     */
    private function createTestFixtureAdvancedPrice(string $discountId, string $currency, float $price, ContainerInterface $container)
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

    /**
     * function creates a discount for a promotion
     */
    private function createTestFixtureDiscount(
        string $promotionId,
        string $discountType,
        string $scope,
        float $value,
        ?float $maxValue,
        ContainerInterface $container,
        SalesChannelContext $context,
        bool $considerAdvancedRules = false): string
    {
        $discountRepository = $container->get('promotion_discount.repository');

        $discountId = Uuid::randomHex();

        $data = [
            'id' => $discountId,
            'promotionId' => $promotionId,
            'scope' => $scope,
            'type' => $discountType,
            'value' => $value,
            'considerAdvancedRules' => $considerAdvancedRules,
        ];

        if ($maxValue !== null) {
            $data['maxValue'] = $maxValue;
        }

        $discountRepository->create([$data], $context->getContext());

        return $discountId;
    }

    /**
     * function creates a promotion
     */
    private function createPromotion(string $promotionId, ?string $code, EntityRepositoryInterface $promotionRepository, SalesChannelContext $context)
    {
        $data = [
            'id' => $promotionId,
            'name' => 'Black Friday',
            'active' => true,
            'useCodes' => false,
            'salesChannels' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'priority' => 1],
            ],
        ];

        if ($code !== null) {
            $data['code'] = $code;
            $data['useCodes'] = true;
        }

        $promotionRepository->create([$data], $context->getContext());
    }

    /**
     * Creates a new promotion with a discount that has a scope DELIVERY
     */
    private function createTestFixtureDeliveryPromotion(
        string $promotionId,
        string $discountType,
        float $value,
        ContainerInterface $container,
        SalesChannelContext $context,
        ?string $code): string
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );

        $deliveryId = $this->createTestFixtureDiscount(
            $promotionId,
            $discountType,
            PromotionDiscountEntity::SCOPE_DELIVERY,
            $value,
            null,
            $container,
            $context
        );

        return $deliveryId;
    }

    /**
     * function creates a promotion and a discount for it.
     * function returns the id of the new discount
     */
    private function createTestFixtureFixedDiscountPromotion(
        string $promotionId,
        float $fixedPrice,
        string $scope,
        ?string $code,
        ContainerInterface $container,
        SalesChannelContext $context,
        bool $considerAdvancedRules = false
    ): string {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context);

        $discountId = $this->createTestFixtureDiscount(
            $promotionId,
            PromotionDiscountEntity::TYPE_FIXED,
            $scope,
            $fixedPrice,
            null,
            $this->getContainer(),
            $context,
            $considerAdvancedRules
        );

        return $discountId;
    }
}
