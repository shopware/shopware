<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class CartOrderLineItemsAddRouteTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->salesChannelId = $this->ids->create('sales-channel');

        $this->browser = $this->createCustomSalesChannelBrowser(['id' => $this->salesChannelId]);
        $this->assignSalesChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email);
        $this->ids->set('customer', $customerId);

        $this->browser->request('POST', '/store-api/account/login', ['email' => $email, 'password' => 'shopware']);

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testProductsOfMyOwnOrderAreAddedToTheCart(): void
    {
        $productId = $this->createProduct();
        $orderId = $this->createOrder($this->ids->get('customer'), [$productId]);

        $this->browser->request('POST', '/store-api/checkout/cart/line-item/order/' . $orderId);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertSame('cart', $response['apiAlias']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame($productId, $response['lineItems'][0]['referencedId']);
    }

    public function testUnavailableProductsAreSkippedInsteadOfFailing(): void
    {
        $availableId = $this->createProduct();
        $deactivatedId = $this->createProduct(active: false);
        $invisibleId = $this->createProduct(visible: false);
        $deletedId = $this->createProduct();

        $orderId = $this->createOrder($this->ids->get('customer'), [$availableId, $deactivatedId, $invisibleId, $deletedId]);

        static::getContainer()->get('product.repository')->delete([['id' => $deletedId]], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/checkout/cart/line-item/order/' . $orderId);

        $content = (string) $this->browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        // a product that is no longer buyable must not break the whole reorder, and must never reach the tax calculation
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $content);
        static::assertCount(1, $response['lineItems'], 'only the still available product may end up in the cart');
        static::assertSame($availableId, $response['lineItems'][0]['referencedId']);
        static::assertNotEmpty($response['errors'], 'the skipped products are reported as cart errors');
    }

    public function testPromotionLineItemsAreNotReAdded(): void
    {
        $productId = $this->createProduct();
        $orderId = $this->createOrder($this->ids->get('customer'), [$productId], withPromotion: true);

        $this->browser->request('POST', '/store-api/checkout/cart/line-item/order/' . $orderId);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response['lineItems']);
        static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $response['lineItems'][0]['type']);
    }

    public function testOrderOfAnotherCustomerIsNotFound(): void
    {
        $productId = $this->createProduct();
        $foreignOrderId = $this->createOrder($this->createCustomer(Uuid::randomHex() . '@example.com'), [$productId]);

        $this->browser->request('POST', '/store-api/checkout/cart/line-item/order/' . $foreignOrderId);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $this->browser->request('POST', '/store-api/checkout/cart/line-item/order/' . Uuid::randomHex());

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    public function testOrderRouteExposesProductAvailabilityToHeadlessClients(): void
    {
        $availableId = $this->createProduct();
        $deactivatedId = $this->createProduct(active: false);

        $this->createOrder($this->ids->get('customer'), [$availableId, $deactivatedId]);

        $this->browser->request(
            'POST',
            '/store-api/order',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['associations' => ['lineItems' => []]], \JSON_THROW_ON_ERROR)
        );

        $content = (string) $this->browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $content);

        $availability = [];
        foreach ($response['orders']['elements'][0]['lineItems'] as $lineItem) {
            $availability[$lineItem['referencedId']] = $lineItem['extensions']['productAvailable']['available'];
        }

        // a headless client gets the same answer the storefront renders from, without querying every product itself
        static::assertTrue($availability[$availableId]);
        static::assertFalse($availability[$deactivatedId]);
    }

    private function createProduct(bool $active = true, bool $visible = true): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test product',
            'active' => $active,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'taxId' => $this->getValidTaxId(),
        ];

        if ($visible) {
            $data['visibilities'] = [
                ['salesChannelId' => $this->salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ];
        }

        static::getContainer()->get('product.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param list<string> $productIds
     */
    private function createOrder(string $customerId, array $productIds, bool $withPromotion = false): string
    {
        $id = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();

        $lineItems = [];
        $position = 1;

        foreach ($productIds as $productId) {
            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'identifier' => $productId,
                'referencedId' => $productId,
                'productId' => $productId,
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'quantity' => 1,
                'label' => 'Test product',
                'good' => true,
                'position' => $position++,
                'payload' => ['productNumber' => $productId],
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
            ];
        }

        if ($withPromotion) {
            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'identifier' => 'promotion',
                'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                'quantity' => 1,
                'label' => 'promotion',
                'position' => $position,
                'price' => new CalculatedPrice(-2, -2, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'priceDefinition' => new QuantityPriceDefinition(-2, new TaxRuleCollection()),
            ];
        }

        static::getContainer()->get('order.repository')->create([[
            'id' => $id,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->getStateMachineState(),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => $this->salesChannelId,
            'billingAddressId' => $billingAddressId,
            'orderNumber' => Uuid::randomHex(),
            'deepLinkCode' => Uuid::randomHex(),
            'addresses' => [[
                'id' => $billingAddressId,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ]],
            'lineItems' => $lineItems,
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ]], Context::createDefaultContext());

        return $id;
    }
}
