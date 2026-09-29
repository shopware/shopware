<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionAlreadyRedeemedTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->addCountriesToSalesChannel();
    }

    /**
     * Reproduces the reported issue: a promotion with a code that is redeemable only
     * once per customer. After the customer redeems it and submits the order, applying
     * the same code again in a new checkout must report a clear "already redeemed"
     * reason instead of the misleading "promotion could not be found" error.
     */
    #[Group('promotions')]
    public function testReapplyingAOnceRedeemedCodeReportsAlreadyRedeemed(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'TESTCODE';
        $customerId = $this->createCustomer();

        // a promotion with a fixed (global) code, redeemable once per customer, with a discount
        $this->promotionRepository->create([[
            'id' => $promotionId,
            'name' => 'Once per customer',
            'active' => true,
            'code' => $promotionCode,
            'useCodes' => true,
            'useIndividualCodes' => false,
            'maxRedemptionsPerCustomer' => 1,
            'salesChannels' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                    'value' => 10,
                    'considerAdvancedRules' => false,
                ],
            ],
        ]], Context::createDefaultContext());

        // 1) first checkout: redeem the code and submit the order
        $firstContext = $this->createCustomerContext($customerId);
        $this->createTestFixtureProduct($productId, 119, 19, static::getContainer(), $firstContext);

        $cart = $this->cartService->getCart($firstContext->getToken(), $firstContext);
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $firstContext);
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $firstContext);

        // the promotion is eligible and applied on the first redemption
        static::assertCount(
            1,
            $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE),
            'Promotion should be applied on the first redemption'
        );

        $this->cartService->order($cart, $firstContext, new RequestDataBag());

        // ensure the per-customer redemption count reflects the placed order
        static::getContainer()->get(PromotionRedemptionUpdater::class)
            ->update([$promotionId], Context::createDefaultContext());

        // 2) new checkout with the same customer: applying the same code again is rejected clearly
        $secondContext = $this->createCustomerContext($customerId);
        $secondCart = $this->cartService->getCart($secondContext->getToken(), $secondContext);
        $secondCart = $this->addProduct($productId, 1, $secondCart, $this->cartService, $secondContext);
        $secondCart = $this->addPromotionCode($promotionCode, $secondCart, $this->cartService, $secondContext);

        $error = $secondCart->getErrors()->get('promotion-not-eligible');
        static::assertInstanceOf(
            PromotionNotEligibleError::class,
            $error,
            'Expected a "not eligible" error on the second redemption attempt'
        );
        static::assertSame(
            'promotion-not-eligible-already-redeemed',
            $error->getMessageKey(),
            'The error must carry the "already redeemed" reason'
        );

        static::assertFalse(
            $secondCart->getErrors()->has('promotion-not-found'),
            'The misleading "promotion not found" error must no longer be used for a redeemed code'
        );

        static::assertCount(
            0,
            $secondCart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE),
            'The exhausted promotion must not be applied again'
        );
    }

    private function createCustomerContext(string $customerId): SalesChannelContext
    {
        return static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $customerId]
        );
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
