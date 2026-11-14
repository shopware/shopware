<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
#[Group('cart')]
class CartDeliveryUpdateRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private \ReflectionProperty $builderProperty;

    private DeliveryBuilder $originalBuilder;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createShippingMethods();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'shippingMethodId' => $this->ids->get('standard'),
            'shippingMethods' => [
                ['id' => $this->ids->get('standard')],
                ['id' => $this->ids->get('express')],
                ['id' => $this->ids->get('inactive')],
            ],
        ]);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createProducts();

        // core builds a single delivery without a key, an extension grouping the positions sets one
        $processor = static::getContainer()->get(DeliveryProcessor::class);
        $this->builderProperty = new \ReflectionProperty(DeliveryProcessor::class, 'builder');
        $this->originalBuilder = $this->builderProperty->getValue($processor);
        $this->builderProperty->setValue($processor, new OneDeliveryPerLineItemBuilder());
    }

    protected function tearDown(): void
    {
        $this->builderProperty->setValue(static::getContainer()->get(DeliveryProcessor::class), $this->originalBuilder);
    }

    public function testSelectedShippingMethodIsCalculatedForItsDelivery(): void
    {
        $this->addProducts('p1', 'p2');

        $cart = $this->selectShippingMethod('p2', $this->ids->get('express'));

        $parcel = $this->getDelivery($cart, 'p1');
        static::assertSame($this->ids->get('standard'), $parcel['shippingMethod']['id']);
        static::assertSame(5.0, (float) $parcel['shippingCosts']['totalPrice']);
        $this->assertDeliveryDate(DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(3, 5)), $parcel);

        $freight = $this->getDelivery($cart, 'p2');
        static::assertSame($this->ids->get('express'), $freight['shippingMethod']['id']);
        static::assertSame(15.0, (float) $freight['shippingCosts']['totalPrice']);
        $this->assertDeliveryDate(DeliveryDate::createFromDeliveryTime($this->createDeliveryTime(1, 2)), $freight);

        static::assertSame(
            [$this->ids->get('p2') => $this->ids->get('express')],
            $cart['extensions'][SelectedDeliveryShippingMethods::KEY]['shippingMethodIds'],
        );

        $this->browser->request('GET', '/store-api/context');
        $context = $this->getJson();
        static::assertSame($this->ids->get('standard'), $context['shippingMethod']['id']);

        $this->browser->request('GET', '/store-api/checkout/cart');
        static::assertSame($this->ids->get('express'), $this->getDelivery($this->getJson(), 'p2')['shippingMethod']['id']);
    }

    public function testNullDropsTheSelection(): void
    {
        $this->addProducts('p1', 'p2');
        $this->selectShippingMethod('p2', $this->ids->get('express'));

        $cart = $this->selectShippingMethod('p2', null);

        static::assertSame($this->ids->get('standard'), $this->getDelivery($cart, 'p2')['shippingMethod']['id']);
        static::assertArrayNotHasKey(SelectedDeliveryShippingMethods::KEY, $cart['extensions']);
    }

    public function testSelectionIsDroppedWhenItsDeliveryDisappears(): void
    {
        $this->addProducts('p1', 'p2');
        $this->selectShippingMethod('p2', $this->ids->get('express'));

        $this->browser->request('POST', '/store-api/checkout/cart/line-item/delete', ['ids' => [$this->ids->get('p2')]]);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertArrayNotHasKey(SelectedDeliveryShippingMethods::KEY, $this->getJson()['extensions']);

        $this->addProducts('p2');
        static::assertSame($this->ids->get('standard'), $this->getDelivery($this->getJson(), 'p2')['shippingMethod']['id']);
    }

    public function testUnavailableShippingMethodBlocksItsDelivery(): void
    {
        $this->addProducts('p1', 'p2');

        $cart = $this->selectShippingMethod('p2', $this->ids->get('inactive'));

        static::assertSame($this->ids->get('inactive'), $this->getDelivery($cart, 'p2')['shippingMethod']['id']);

        $blockedIds = [];
        foreach ($cart['errors'] as $error) {
            if ($error['messageKey'] === 'shipping-method-blocked') {
                $blockedIds[] = $error['parameters']['id'];
            }
        }
        static::assertSame([$this->ids->get('inactive')], $blockedIds);
    }

    public function testUnknownDeliveryKeyIsRejected(): void
    {
        $this->addProducts('p1');

        $this->sendSelection('p2', $this->ids->get('express'));

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__CART_DELIVERY_POSITION_NOT_FOUND', $this->getJson()['errors'][0]['code']);
    }

    public function testShippingMethodOfAnotherSalesChannelIsRejected(): void
    {
        $this->addProducts('p1');
        static::getContainer()->get('shipping_method.repository')->create(
            [$this->createShippingMethodData('other-sales-channel', price: 1, minDays: 1, maxDays: 2)],
            Context::createDefaultContext()
        );

        $this->sendSelection('p1', $this->ids->get('other-sales-channel'));

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertSame('/deliveries/0/shippingMethodId', $this->getJson()['errors'][0]['source']['pointer']);
    }

    public function testGuestSelectionWinsOverTheSavedCustomerCartOnLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email);
        $this->createSavedCustomerCart($customerId, [
            'p1' => $this->ids->get('express'),
            'p2' => $this->ids->get('express'),
        ]);

        $this->addProducts('p2');
        $this->selectShippingMethod('p2', $this->ids->get('standard'));

        $this->browser->request('POST', '/store-api/account/login', ['email' => $email, 'password' => 'shopware']);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        $customerToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($customerToken);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $customerToken);

        $this->browser->request('GET', '/store-api/checkout/cart');
        $cart = $this->getJson();

        static::assertSame($this->ids->get('express'), $this->getDelivery($cart, 'p1')['shippingMethod']['id']);
        static::assertSame($this->ids->get('standard'), $this->getDelivery($cart, 'p2')['shippingMethod']['id']);
    }

    private function addProducts(string ...$keys): void
    {
        $items = array_map(fn (string $key) => [
            'id' => $this->ids->get($key),
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'referencedId' => $this->ids->get($key),
        ], $keys);

        $this->browser->jsonRequest('POST', '/store-api/checkout/cart/line-item', ['items' => $items]);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function selectShippingMethod(string $productKey, ?string $shippingMethodId): array
    {
        $this->sendSelection($productKey, $shippingMethodId);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        return $this->getJson();
    }

    private function sendSelection(string $productKey, ?string $shippingMethodId): void
    {
        $this->browser->jsonRequest('PATCH', '/store-api/checkout/cart/delivery', [
            'deliveries' => [['key' => $this->ids->get($productKey), 'shippingMethodId' => $shippingMethodId]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(): array
    {
        return json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $cart
     *
     * @return array<string, mixed>
     */
    private function getDelivery(array $cart, string $productKey): array
    {
        foreach ($cart['deliveries'] as $delivery) {
            if ($delivery['payload'][Delivery::DELIVERY_KEY] === $this->ids->get($productKey)) {
                return $delivery;
            }
        }

        static::fail('No delivery for ' . $productKey);
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function assertDeliveryDate(DeliveryDate $expected, array $delivery): void
    {
        static::assertStringStartsWith($expected->getEarliest()->format('Y-m-d'), $delivery['deliveryDate']['earliest']);
        static::assertStringStartsWith($expected->getLatest()->format('Y-m-d'), $delivery['deliveryDate']['latest']);
    }

    private function createDeliveryTime(int $min, int $max): DeliveryTime
    {
        return DeliveryTime::createFromEntity((new DeliveryTimeEntity())->assign([
            'min' => $min,
            'max' => $max,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ]));
    }

    /**
     * @param array<string, string> $selection
     */
    private function createSavedCustomerCart(string $customerId, array $selection): void
    {
        $token = Uuid::randomHex();
        static::getContainer()->get(SalesChannelContextPersister::class)
            ->save($token, [], $this->ids->get('sales-channel'), $customerId);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, $this->ids->get('sales-channel'), [SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $cart = new Cart($token);
        foreach (['p1', 'p2'] as $key) {
            $cart->add((new LineItem($this->ids->get($key), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->ids->get($key)))->setStackable(true));
        }
        $cart->addExtension(SelectedDeliveryShippingMethods::KEY, new SelectedDeliveryShippingMethods(array_combine(
            array_map(fn (string $key) => $this->ids->get($key), array_keys($selection)),
            $selection,
        )));

        static::getContainer()->get(CartPersister::class)->save($cart, $context);
    }

    private function createShippingMethods(): void
    {
        static::getContainer()->get('shipping_method.repository')->create([
            $this->createShippingMethodData('standard', price: 5, minDays: 3, maxDays: 5),
            $this->createShippingMethodData('express', price: 15, minDays: 1, maxDays: 2),
            [...$this->createShippingMethodData('inactive', price: 1, minDays: 1, maxDays: 2), 'active' => false],
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function createShippingMethodData(string $key, float $price, int $minDays, int $maxDays): array
    {
        return [
            'id' => $this->ids->create($key),
            'name' => $key,
            'technicalName' => $key . '-' . $this->ids->get($key),
            'active' => true,
            'deliveryTime' => [
                'name' => $key,
                'min' => $minDays,
                'max' => $maxDays,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'prices' => [[
                'calculation' => 1,
                'quantityStart' => 1,
                'currencyPrice' => [['currencyId' => Defaults::CURRENCY, 'net' => $price, 'gross' => $price, 'linked' => false]],
            ]],
        ];
    }

    private function createProducts(): void
    {
        $products = array_map(fn (string $key) => [
            'id' => $this->ids->create($key),
            'productNumber' => $this->ids->get($key),
            'stock' => 10,
            'name' => $key,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ], ['p1', 'p2']);

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }
}

/**
 * @internal
 */
class OneDeliveryPerLineItemBuilder extends DeliveryBuilder
{
    public function build(Cart $cart, CartDataCollection $data, SalesChannelContext $context, CartBehavior $cartBehavior): DeliveryCollection
    {
        $deliveries = new DeliveryCollection();

        foreach ($cart->getLineItems() as $lineItem) {
            $lineItemCart = new Cart($cart->getToken());
            $lineItemCart->setLineItems(new LineItemCollection([$lineItem]));

            foreach (parent::build($lineItemCart, $data, $context, $cartBehavior) as $delivery) {
                $delivery->setPayloadValue(Delivery::DELIVERY_KEY, $lineItem->getId());
                $deliveries->add($delivery);
            }
        }

        return $deliveries;
    }
}
