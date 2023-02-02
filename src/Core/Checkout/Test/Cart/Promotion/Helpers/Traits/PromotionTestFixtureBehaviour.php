<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Package('checkout')]
trait PromotionTestFixtureBehaviour
{
    use TaxAddToSalesChannelTestBehaviour;

    public function createSetGroupFixture(string $packagerKey, int $value, string $sorterKey, string $promotionId, ContainerInterface $container): string
    {
        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $repository = $container->get('promotion_setgroup.repository');

        $groupId = Uuid::randomHex();

        $data = [
            'id' => $groupId,
            'promotionId' => $promotionId,
            'packagerKey' => $packagerKey,
            'sorterKey' => $sorterKey,
            'value' => $value,
        ];

        $repository->create([$data], $context->getContext());

        return $groupId;
    }

    /**
     * Creates a new product in the database.
     */
    private function createTestFixtureProduct(string $productId, float $grossPrice, float $taxRate, ContainerInterface $container, SalesChannelContext $context): void
    {
        /** @var EntityRepository $productRepository */
        $productRepository = $container->get('product.repository');

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => $taxRate, 'name' => 'with id'];

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
                    'tax' => $tax,
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

        $this->addTaxDataToSalesChannel($context, $tax);
    }

    /**
     * Creates a new absolute promotion in the database.
     */
    private function createTestFixtureAbsolutePromotion(string $promotionId, string $code, float $value, ContainerInterface $container, string $scope = PromotionDiscountEntity::SCOPE_CART): string
    {
        /** @var EntityRepository $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

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
     */
    private function createTestFixturePercentagePromotion(string $promotionId, ?string $code, float $percentage, ?float $maxValue, ContainerInterface $container, string $scope = PromotionDiscountEntity::SCOPE_CART): string
    {
        /** @var EntityRepository $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );

        return $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, $scope, $percentage, $maxValue, $container, $context);
    }

    /**
     * Creates a new percentage promotion in the database.
     */
    private function createTestFixtureSetGroupPromotion(string $promotionId, ?string $code, ContainerInterface $container): void
    {
        /** @var EntityRepository $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createSetGroupPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );
    }

    private function createSetGroupDiscount(
        string $promotionId,
        int $groupIndex,
        ContainerInterface $container,
        float $value,
        ?float $maxValue,
        string $discountType = PromotionDiscountEntity::TYPE_PERCENTAGE,
        string $sortKey = 'PRICE_ASC',
        string $applierKey = 'ALL',
        string $usageKey = 'ALL',
        string $pickerKey = 'VERTICAL'
    ): string {
        $scope = PromotionDiscountEntity::SCOPE_SETGROUP . '-' . $groupIndex;

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        /** @var EntityRepository $discountRepository */
        $discountRepository = $container->get('promotion_discount.repository');

        $discountId = Uuid::randomHex();

        $data = [
            'id' => $discountId,
            'promotionId' => $promotionId,
            'scope' => $scope,
            'type' => $discountType,
            'value' => $value,
            'considerAdvancedRules' => true,
            'sorterKey' => $sortKey,
            'applierKey' => $applierKey,
            'usageKey' => $usageKey,
            'pickerKey' => $pickerKey,
        ];

        if ($maxValue !== null) {
            $data['maxValue'] = $maxValue;
        }

        $discountRepository->create([$data], $context->getContext());

        return $discountId;
    }

    /**
     * Creates a new advanced currency price for the provided discount
     */
    private function createTestFixtureAdvancedPrice(string $discountId, string $currency, float $price, ContainerInterface $container): void
    {
        /** @var EntityRepository $pricesRepository */
        $pricesRepository = $container->get('promotion_discount_prices.repository');

        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

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
        bool $considerAdvancedRules = false
    ): string {
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
    private function createPromotion(string $promotionId, ?string $code, EntityRepository $promotionRepository, SalesChannelContext $context): EntityWrittenContainerEvent
    {
        $data = [
            'id' => $promotionId,
        ];

        if ($code !== null) {
            $data['code'] = $code;
            $data['useCodes'] = true;
        }

        return $this->createPromotionWithCustomData($data, $promotionRepository, $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createPromotionWithCustomData(array $data, EntityRepository $promotionRepository, SalesChannelContext $context): EntityWrittenContainerEvent
    {
        $data = array_merge([
            'id' => Uuid::randomHex(),
            'name' => 'Black Friday',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => false,
            'salesChannels' => [
                ['salesChannelId' => $context->getSalesChannel()->getId(), 'priority' => 1],
            ],
        ], $data);

        return $promotionRepository->create([$data], $context->getContext());
    }

    /**
     * function creates an individual promotion code
     */
    private function createIndividualCode(string $promotionId, ?string $code, EntityRepository $promotionIndividualRepository, Context $context): EntityWrittenContainerEvent
    {
        $data = [
            'id' => Uuid::randomHex(),
            'promotionId' => $promotionId,
            'code' => $code,
        ];

        return $promotionIndividualRepository->create([$data], $context);
    }

    private function createSetGroupPromotion(string $promotionId, ?string $code, EntityRepository $promotionRepository, SalesChannelContext $context): void
    {
        $data = [
            'id' => $promotionId,
            'name' => 'Black Friday',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => true,
            'salesChannels' => [
                ['salesChannelId' => $context->getSalesChannel()->getId(), 'priority' => 1],
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
        ?string $code
    ): string {
        /** @var EntityRepository $promotionRepository */
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
    private function createTestFixtureFixedUnitDiscountPromotion(
        string $promotionId,
        float $fixedPrice,
        string $scope,
        ?string $code,
        ContainerInterface $container,
        SalesChannelContext $context,
        bool $considerAdvancedRules = false
    ): string {
        /** @var EntityRepository $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );

        $discountId = $this->createTestFixtureDiscount(
            $promotionId,
            PromotionDiscountEntity::TYPE_FIXED_UNIT,
            $scope,
            $fixedPrice,
            null,
            $this->getContainer(),
            $context,
            $considerAdvancedRules
        );

        return $discountId;
    }

    /**
     * function creates a promotion and a discount for it.
     * function returns the id of the new discount
     */
    private function createTestFixtureFixedDiscountPromotion(string $promotionId, float $fixedPrice, string $scope, ?string $code, ContainerInterface $container, SalesChannelContext $context): string
    {
        /** @var EntityRepository $promotionRepository */
        $promotionRepository = $container->get('promotion.repository');

        $this->createPromotion(
            $promotionId,
            $code,
            $promotionRepository,
            $context
        );

        $discountId = $this->createTestFixtureDiscount(
            $promotionId,
            PromotionDiscountEntity::TYPE_FIXED,
            $scope,
            $fixedPrice,
            null,
            $this->getContainer(),
            $context,
            false
        );

        return $discountId;
    }
}
