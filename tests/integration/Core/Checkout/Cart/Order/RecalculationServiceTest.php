<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Rule\TrueRule;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('slow')]
#[Package('checkout')]
class RecalculationServiceTest extends TestCase
{
    use AdminApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    protected SalesChannelContext $salesChannelContext;

    protected Context $context;

    protected string $customerId;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private EntityRepository $shippingMethodRepository;

    /**
     * @var EntityRepository<OrderDeliveryCollection>
     */
    private EntityRepository $orderDeliveryRepository;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
        $this->orderDeliveryRepository = static::getContainer()->get('order_delivery.repository');

        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);
        $this->addCountriesToSalesChannel([$this->getValidCountryIdWithTaxes()]);
        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
    }

    #[DataProvider('customLineItemProvider')]
    public function testAddCustomLineItemSdf(LineItem $lineItem, int $positionCount): void
    {
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];
        $versionId = $this->createVersionedOrder($orderId);
        $context = Context::createDefaultContext()->createWithVersionId($versionId);

        $this->getContainer()->get(RecalculationService::class)->addCustomLineItem($orderId, $lineItem, $context);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.positions');

        $order = $this->orderRepository->search($criteria, $context)->get($orderId);
        static::assertNotNull($order);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        static::assertCount(3, $lineItems);

        $positions = $order->getDeliveries()?->first()?->getPositions();
        static::assertNotNull($positions);
        static::assertCount($positionCount, $positions);
    }

    public static function customLineItemProvider(): \Generator
    {
        yield 'line item type custom, shipping cost aware' => [
            (new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setLabel('Test custom line item')
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([new TaxRule(19)]))),
            3,
        ];

        yield 'line item type custom, not shipping cost aware' => [
            (new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setLabel('Test custom line item')
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([new TaxRule(19)])))
                ->setStates([State::IS_DOWNLOAD]),
            2,
        ];
    }

    public function testPersistOrderAndConvertToCart(): void
    {
        $parentProductId = Uuid::randomHex();
        $childProductId = Uuid::randomHex();
        // to test the sorting, the parentId has to be greater than the rootId
        $parentProductId = substr_replace($parentProductId, '0', 0, 1);
        $rootProductId = substr_replace($parentProductId, 'f', 0, 1);

        $cart = $this->generateDemoCart($parentProductId, $rootProductId);

        $cart = $this->addProduct($cart, $childProductId);

        $product1 = $cart->get($parentProductId);
        $product2 = $cart->get($childProductId);

        static::assertNotNull($product1);
        static::assertNotNull($product2);

        $product1->getChildren()->add($product2);
        $cart->remove($childProductId);

        $cart = static::getContainer()->get(Processor::class)
            ->process($cart, $this->salesChannelContext, new CartBehavior());

        $orderId = $this->persistCart($cart)['orderId'];

        $deliveryCriteria = new Criteria();
        $deliveryCriteria->addAssociation('positions');

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod.tax')
            ->addAssociation('deliveries.shippingMethod.deliveryTime')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $this->context)->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getNestedLineItems());

        // check lineItem sorting
        $idx = 0;
        foreach ($order->getNestedLineItems() as $lineItem) {
            if ($idx === 0) {
                static::assertSame($parentProductId, $lineItem->getReferencedId());
            } else {
                static::assertSame($rootProductId, $lineItem->getReferencedId());
            }
            ++$idx;
        }

        $convertedCart = static::getContainer()->get(OrderConverter::class)->convertToCart($order, $this->context);

        // check token
        static::assertNotSame($cart->getToken(), $convertedCart->getToken());
        static::assertTrue(Uuid::isValid($convertedCart->getToken()));

        // check lineItem sorting
        $idx = 0;
        foreach ($convertedCart->getLineItems() as $lineItem) {
            if ($idx === 0) {
                static::assertSame($parentProductId, $lineItem->getId());
            } else {
                static::assertSame($rootProductId, $lineItem->getId());
            }
            ++$idx;
        }
        // set token to be equal for further comparison
        $cart->setToken($convertedCart->getToken());

        // transactions are currently not supported so they are excluded for comparison
        $cart->setTransactions(new TransactionCollection());

        $this->removeExtensions($cart);
        $this->removeExtensions($convertedCart);

        // remove delivery information from line items

        foreach ($cart->getDeliveries() as $delivery) {
            // remove address from ShippingLocation
            $property = ReflectionHelper::getProperty(ShippingLocation::class, 'address');
            $property->setValue($delivery->getLocation(), null);

            foreach ($delivery->getPositions() as $position) {
                $position->getLineItem()->setDeliveryInformation(null);
                $position->getLineItem()->setQuantityInformation(null);

                foreach ($position->getLineItem()->getChildren() as $lineItem) {
                    $lineItem->setDeliveryInformation(null);
                    $lineItem->setQuantityInformation(null);
                }
            }

            $delivery->getShippingMethod()->setPrices(new ShippingMethodPriceCollection());
        }

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->setDeliveryInformation(null);
            $lineItem->setQuantityInformation(null);
        }

        $this->resetDataTimestamps($cart->getLineItems());
        foreach ($cart->getDeliveries() as $delivery) {
            $this->resetDataTimestamps($delivery->getPositions()->getLineItems());
        }
        $cart->setRuleIds([]);
        // The behaviour will be set during the process, therefore we remove it here
        $cart->setBehavior(null);

        // unique identifier is set at runtime to be random uuid
        foreach ($convertedCart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->assign(['uniqueIdentifier' => 'foo']);
        }

        foreach ($convertedCart->getDeliveries() as $delivery) {
            foreach ($delivery->getPositions() as $position) {
                $position->getLineItem()->assign(['uniqueIdentifier' => 'foo']);

                foreach ($position->getLineItem()->getChildren()->getFlat() as $lineItem) {
                    $lineItem->assign(['uniqueIdentifier' => 'foo']);
                }
            }
        }

        $this->resetPayloadProtection($cart);
        $this->resetPayloadProtection($convertedCart);

        static::assertEquals($cart, $convertedCart);
    }

    public function testRecalculationController(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getOrderCustomer());

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testRecalculationControllerWithNonSystemLanguage(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart, $this->getDeDeLanguageId())['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->get($orderId);
        static::assertNotNull($order);

        static::assertSame($this->getDeDeLanguageId(), $order->getLanguageId());
    }

    public function testFetchOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        static::expectException(OrderException::class);
        static::expectExceptionMessage("Order with id $orderId can not be recalculated because it is in the live version. Please create a new version");

        $service = static::getContainer()->get(RecalculationService::class);

        (new \ReflectionClass($service))
            ->getMethod('fetchOrder')
            ->invoke($service, $orderId, $this->context);
    }

    public function testRecalculationWithDeletedCustomer(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        static::getContainer()->get('customer.repository')->delete([['id' => $this->customerId]], $this->context);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getOrderCustomer());

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddProductToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $order = $this->persistCart($cart);
        $orderId = $order['orderId'];
        $oldTotal = $order['total'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $productName = 'Test';
        $productPrice = 10.0;
        $productTaxRate = 19.0;
        $this->addProductToVersionedOrder($productName, $productPrice, $productTaxRate, $orderId, $versionId, $oldTotal);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first();
        static::assertNotNull($delivery);
        $newShippingCosts = $delivery->getShippingCosts();

        $firstTax = $newShippingCosts->getCalculatedTaxes()->first();
        $lastTax = $newShippingCosts->getCalculatedTaxes()->last();

        // tax is now mixed
        static::assertCount(2, $newShippingCosts->getCalculatedTaxes());
        static::assertNotNull($firstTax);
        static::assertSame(19.0, $firstTax->getTaxRate());
        static::assertNotNull($lastTax);
        static::assertSame(5.0, $lastTax->getTaxRate());
    }

    public function testAddProductToOrderWithCustomerComment(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $cart->setCustomerComment('test comment');
        $cart->setAffiliateCode('test_affiliate_code');
        $cart->setCampaignCode('test_campaign_code');
        $order = $this->persistCart($cart);

        $orderId = $order['orderId'];
        $oldTotal = $order['total'];
        $oldOrderStateId = $order['stateId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $productName = 'Test';
        $productPrice = 10.0;
        $productTaxRate = 19.0;

        $productId = $this->createProduct($productName, $productPrice, $productTaxRate);

        // add product to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/product/%s',
                $orderId,
                $productId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame('test comment', $order->getCustomerComment());
        static::assertSame('test_affiliate_code', $order->getAffiliateCode());
        static::assertSame('test_campaign_code', $order->getCampaignCode());
        static::assertSame($oldOrderStateId, $order->getStateId());

        $product = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $productId) {
                $product = $lineItem;
            }
        }

        static::assertNotNull($product);
        static::assertNotNull($product->getPrice());
        $productPriceInclTax = 10 + ($productPrice * $productTaxRate / 100);
        static::assertSame($product->getPrice()->getUnitPrice(), $productPriceInclTax);
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);

        static::assertNotNull($order->getAmountTotal());
        static::assertSame($oldTotal + $productPriceInclTax, $order->getAmountTotal());
    }

    public function testAddProductToOrderTriggersStockUpdate(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $order = $this->persistCart($cart);
        $orderId = $order['orderId'];
        $oldTotal = $order['total'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $productName = 'Test';
        $productPrice = 10.0;
        $productTaxRate = 19.0;
        $productId = $this->addProductToVersionedOrder($productName, $productPrice, $productTaxRate, $orderId, $versionId, $oldTotal);

        $this->orderRepository
            ->merge($versionId, Context::createDefaultContext());

        $stocks = static::getContainer()->get(Connection::class)
            ->fetchAssociative('SELECT stock, available_stock FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($productId)]);

        static::assertIsArray($stocks);

        static::assertSame(4, (int) $stocks['stock']);
        static::assertSame(4, (int) $stocks['available_stock']);
    }

    public function testAddCustomLineItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'total' => $oldTotal, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->addCustomLineItemToVersionedOrder($orderId, $versionId, $oldTotal, $orderDateTime, $stateId);
    }

    public function testAddCreditItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'total' => $total, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->addCreditItemToVersionedOrder($orderId, $versionId, $total, $orderDateTime, $stateId);
    }

    public function testAddPromotionItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create a promotion code with discount
        $code = 'GET5';
        $discountValue = 5.0;
        $this->createPromotion($discountValue, $code);

        $this->addPromotionItemToVersionedOrder($orderId, $versionId, $code, $orderDateTime, $stateId);
    }

    public function testAddNonExistingPromotionItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/promotion-item',
                $orderId
            ),
            server: ['HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId],
            content: (string) json_encode(['code' => 'some-random-code'], \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame($errors[0]['message'], 'Promotion with code some-random-code not found!');
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testToggleAutomaticPromotions(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create an automatic promotion with discount
        $discountValue = 5.0;
        $promotionId = $this->createPromotion($discountValue);

        $this->toggleAutomaticPromotions($orderId, $versionId, $promotionId, $orderDateTime, $stateId);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testToggleAutomaticPromotionsForDelivery(): void
    {
        // create order
        $cart = $this->generateDemoCart();

        $shippingMethod = $this->shippingMethodRepository->search(new Criteria(), $this->context)->first();
        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);

        $cart->setDeliveries(new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(new \DateTime(), new \DateTime()),
                $shippingMethod,
                new ShippingLocation(new CountryEntity(), null, $this->getCustomerAddress(Uuid::randomHex())),
                new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
            ),
        ]));

        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $promotionId = $this->createShippingDiscount(100);

        $this->toggleAutomaticPromotionsForDelivery($orderId, $versionId, $promotionId, $orderDateTime, $stateId);
    }

    public function testApplyAutomaticPromotions(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create an automatic promotion with discount
        $discountValue = 5.0;
        $promotionId = $this->createPromotion($discountValue);

        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);
        $promotionItem = $order->getLineItems()?->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first();

        static::assertCount(1, $content['errors']);
        static::assertNotNull($promotionItem);
        static::assertSame('Discount auto promotion has been added', array_values($content['errors'])[0]['message']);
        static::assertSame($order->getStateId(), $stateId);

        // On recalculation, promotion is applied once more, creating a new line item.
        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);
        $newPromotionItem = $order->getLineItems()?->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first();

        static::assertEmpty($content['errors']);
        static::assertNotNull($newPromotionItem);
        static::assertSame($promotionItem->getId(), $newPromotionItem->getId(), 'line-item id of promotion should not differ between recalculations');
        static::assertSame($promotionItem->getPayload(), $newPromotionItem->getPayload());
    }

    public function testApplyAutomaticShippingPromotions(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create an automatic promotion with discount
        $promotionId = $this->createShippingDiscount(5.0);

        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);

        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame('Discount delivery promotion has been added', $errors[0]['message']);
        static::assertSame($order->getStateId(), $stateId);

        static::assertNotNull($order->getDeliveries());
        $deliveryIds = $order->getDeliveries()->getKeys();
        static::assertCount(2, $deliveryIds);
        $promotionDelivery = $order->getDeliveries()->get($deliveryIds[1]);
        static::assertSame(-0.5, $promotionDelivery?->getShippingCosts()->getTotalPrice());

        // On recalculation, promotion is applied once more, creating a new delivery.
        // The old one is expected to be deleted.
        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);
        static::assertEmpty($content['errors']);
        static::assertNotNull($order->getDeliveries());
        $deliveryIds = $order->getDeliveries()->getKeys();
        static::assertCount(2, $deliveryIds);
        $newPromotionDelivery = $order->getDeliveries()->get($deliveryIds[1]);
        static::assertSame(-0.5, $newPromotionDelivery?->getShippingCosts()->getTotalPrice());

        static::assertNotSame($newPromotionDelivery->getId(), $promotionDelivery->getId());
    }

    public function testRecalculationOfPinnedDisabledPromotion(): void
    {
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        $promotionId = $this->createPromotion(10.0, 'GET5', PromotionDiscountEntity::TYPE_PERCENTAGE);

        $versionId = $this->createVersionedOrder($orderId);
        $order = $this->addPromotionItemToVersionedOrder($orderId, $versionId, 'GET5', $orderDateTime, $stateId);

        static::assertSame(225.98, $order->getAmountTotal());

        static::getContainer()->get('promotion.repository')->upsert(
            [['id' => $promotionId, 'active' => false]],
            $this->context,
        );

        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            server: [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertNotNull($order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertSame(225.98, $order->getAmountTotal());
    }

    public function testRecalculationOfPinnedPromotionWithProductAdded(): void
    {
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        $promotionId = $this->createPromotion(10.0, 'GET5', PromotionDiscountEntity::TYPE_PERCENTAGE);

        $versionId = $this->createVersionedOrder($orderId);
        $order = $this->addPromotionItemToVersionedOrder($orderId, $versionId, 'GET5', $orderDateTime, $stateId);

        static::assertSame(225.98, $order->getAmountTotal());

        static::getContainer()->get('promotion.repository')->upsert(
            [['id' => $promotionId, 'active' => false]],
            $this->context,
        );

        $this->addProductToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, 224.79);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getLineItems());
        static::assertCount(4, $order->getLineItems());
        static::assertNotNull($order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertNotSame(237.17, $order->getAmountTotal(), 'Promotion of order isn\'t recalculated');
        static::assertSame(236.69, $order->getAmountTotal());
    }

    public function testRecalculationOfPinnedAutomaticDisabledPromotion(): void
    {
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId] = $this->persistCart($cart);
        $versionId = $this->createVersionedOrder($orderId);

        $promotionId = $this->createPromotion(10.0, null, PromotionDiscountEntity::TYPE_PERCENTAGE);

        [$order] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);

        static::assertSame(225.98, $order->getAmountTotal());

        static::getContainer()->get('promotion.repository')->upsert(
            [['id' => $promotionId, 'active' => false]],
            $this->context,
        );

        $this->addProductToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, 224.79);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getLineItems());
        static::assertCount(4, $order->getLineItems());
        static::assertNotNull($order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertSame(236.69, $order->getAmountTotal());

        // as promotion is disabled, it should be removed again
        [$order] = $this->applyAutomaticPromotions($orderId, $versionId, null);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertSame(261.88, $order->getAmountTotal());
    }

    public function testCreatedVersionedOrderAndMerge(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'total' => $oldTotal, 'orderDateTime' => $orderDateTime] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $productName = 'Test';
        $productPrice = 10.0;
        $productTaxRate = 19.0;
        $productId = $this->addProductToVersionedOrder(
            $productName,
            $productPrice,
            $productTaxRate,
            $orderId,
            $versionId,
            $oldTotal
        );

        // merge versioned order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/version/merge/%s/%s',
                static::getContainer()->get(OrderDefinition::class)->getEntityName(),
                $versionId
            )
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read merged order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context)->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());

        $product = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $productId) {
                $product = $lineItem;
            }
        }

        static::assertNotNull($product);
        static::assertNotNull($product->getPrice());
        $productPriceInclTax = 10 + ($productPrice * $productTaxRate / 100);
        static::assertSame($product->getPrice()->getUnitPrice(), $productPriceInclTax);
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);
        static::assertNotNull($order->getOrderDateTime());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testChangeShippingCosts(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $deliveries = $this->orderDeliveryRepository->search($criteria, $versionContext);

        static::assertCount(1, $deliveries);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $shippingCosts = $delivery->getShippingCosts();

        static::assertSame(1, $shippingCosts->getQuantity());
        static::assertSame(10.0, $shippingCosts->getUnitPrice());
        static::assertSame(10.0, $shippingCosts->getTotalPrice());
        static::assertCount(2, $shippingCosts->getCalculatedTaxes());

        // change shipping costs
        $newShippingCosts = new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection());

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);

        $payload = [
            'id' => $delivery->getId(),
            'shippingCosts' => $newShippingCosts,
        ];

        $this->orderDeliveryRepository->upsert([$payload], $versionContext);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $deliveries = $this->orderDeliveryRepository->search($criteria, $versionContext);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $newShippingCosts = $delivery->getShippingCosts();

        static::assertSame(1, $newShippingCosts->getQuantity());
        static::assertSame(5.0, $newShippingCosts->getUnitPrice());
        static::assertSame(5.0, $newShippingCosts->getTotalPrice());

        $firstTax = $newShippingCosts->getCalculatedTaxes()->first();
        $lastTax = $newShippingCosts->getCalculatedTaxes()->last();

        // tax is now mixed
        static::assertCount(2, $newShippingCosts->getCalculatedTaxes());
        static::assertNotNull($firstTax);
        static::assertSame(19.0, $firstTax->getTaxRate());
        static::assertNotNull($lastTax);
        static::assertSame(5.0, $lastTax->getTaxRate());
    }

    public function testDeleteLineItemsAfterRecalculateOrderWitchInactiveProducts(): void
    {
        // Arrange
        $inactiveProductId = Uuid::randomHex();

        $cart = $this->generateDemoCart($inactiveProductId);
        $orderId = $this->persistCart($cart)['orderId'];

        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $this->context)->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItemWithInactiveProduct = $order->getLineItems()?->filter(
            static fn (OrderLineItemEntity $lineItem) => $lineItem->getIdentifier() === $inactiveProductId
        )->first();

        static::assertNotNull($lineItemWithInactiveProduct);

        static::getContainer()->get('product.repository')->update([['id' => $inactiveProductId, 'active' => false]], $this->context);

        $options = [
            SalesChannelContextService::PERMISSIONS => [
                ...OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
                ProductCartProcessor::KEEP_INACTIVE_PRODUCT => false,
            ],
        ];

        // Act
        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext, $options);

        // Assert
        $order = $this->orderRepository->search($criteria, $versionContext)->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItemWithInactiveProduct = $order->getLineItems()?->filter(
            static fn (OrderLineItemEntity $lineItem) => $lineItem->getIdentifier() === $inactiveProductId
        )->first();

        static::assertNull($lineItemWithInactiveProduct);
    }

    public function testRecalculateOrderWithInactiveProduct(): void
    {
        $inactiveProductId = Uuid::randomHex();
        // create order
        $cart = $this->generateDemoCart($inactiveProductId);
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $this->context)->get($orderId);
        static::assertNotNull($order);

        static::assertSame(224.07, $order->getPrice()->getNetPrice());
        static::assertSame(249.98, $order->getPrice()->getTotalPrice());
        static::assertSame(239.98, $order->getPrice()->getPositionPrice());

        static::getContainer()->get('product.repository')->update([['id' => $inactiveProductId, 'active' => false]], $this->context);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $order = $this->orderRepository->search($criteria, $this->context)->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getPrice());

        static::assertSame(224.07, $order->getPrice()->getNetPrice());
        static::assertSame(249.98, $order->getPrice()->getTotalPrice());
        static::assertSame(239.98, $order->getPrice()->getPositionPrice());
    }

    public function testForeachLoopInCalculateDeliveryFunction(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->addSecondPriceRuleToShippingMethod($priceRuleId, $shippingMethodId);
        $this->salesChannelContext->setRuleIds(array_merge($this->salesChannelContext->getRuleIds(), [$priceRuleId]));

        $prop = ReflectionHelper::getProperty(SalesChannelContext::class, 'shippingMethod');
        $prop->setValue($this->salesChannelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $shippingCosts = $this->orderDeliveryRepository->search($criteria, $versionContext)->first()?->getShippingCosts();
        static::assertNotNull($shippingCosts);

        static::assertSame(1, $shippingCosts->getQuantity());
        static::assertSame(15.0, $shippingCosts->getUnitPrice());
        static::assertSame(15.0, $shippingCosts->getTotalPrice());
    }

    public function testStartAndEndConditionsInPriceRule(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->addSecondShippingMethodPriceRule($priceRuleId, $shippingMethodId);
        $this->salesChannelContext->setRuleIds(array_merge($this->salesChannelContext->getRuleIds(), [$priceRuleId]));

        $prop = ReflectionHelper::getProperty(SalesChannelContext::class, 'shippingMethod');
        $prop->setValue($this->salesChannelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->getAssociation('shippingMethod')->addAssociation('prices');

        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $shippingMethod = $this->orderDeliveryRepository->search($criteria, $versionContext)->first()?->getShippingMethod();
        static::assertNotNull($shippingMethod);

        $firstPriceRule = $shippingMethod->getPrices()->first();
        static::assertNotNull($firstPriceRule);
        $secondPriceRule = $shippingMethod->getPrices()->last();
        static::assertNotNull($secondPriceRule);

        static::assertSame($firstPriceRule->getRuleId(), $secondPriceRule->getRuleId());
        static::assertGreaterThan($firstPriceRule->getQuantityStart(), $firstPriceRule->getQuantityEnd());
        static::assertGreaterThan($firstPriceRule->getQuantityEnd(), $secondPriceRule->getQuantityStart());
        static::assertGreaterThan($secondPriceRule->getQuantityStart(), $secondPriceRule->getQuantityEnd());
    }

    public function testIfCorrectConditionIsUsedCalculationByLineItemCount(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->addSecondShippingMethodPriceRule($priceRuleId, $shippingMethodId);
        $this->salesChannelContext->setRuleIds(array_merge($this->salesChannelContext->getRuleIds(), [$priceRuleId]));

        $prop = ReflectionHelper::getProperty(SalesChannelContext::class, 'shippingMethod');
        $prop->setValue($this->salesChannelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->first();
        static::assertNotNull($delivery);
        static::assertSame(1, $delivery->getShippingCosts()->getQuantity());
        static::assertSame(15.0, $delivery->getShippingCosts()->getUnitPrice());
        static::assertSame(15.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testIfCorrectConditionIsUsedPriceCalculation(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->createTwoConditionsWithDifferentQuantities($priceRuleId, $shippingMethodId, DeliveryCalculator::CALCULATION_BY_PRICE);
        $this->salesChannelContext->setRuleIds(array_merge($this->salesChannelContext->getRuleIds(), [$priceRuleId]));

        $prop = ReflectionHelper::getProperty(SalesChannelContext::class, 'shippingMethod');
        $prop->setValue($this->salesChannelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->first();
        static::assertNotNull($delivery);
        static::assertSame(1, $delivery->getShippingCosts()->getQuantity());
        static::assertSame(9.99, $delivery->getShippingCosts()->getUnitPrice());
        static::assertSame(9.99, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testIfCorrectConditionIsUsedWeightCalculation(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->createTwoConditionsWithDifferentQuantities($priceRuleId, $shippingMethodId, DeliveryCalculator::CALCULATION_BY_WEIGHT);
        $this->salesChannelContext->setRuleIds(array_merge($this->salesChannelContext->getRuleIds(), [$priceRuleId]));

        $prop = ReflectionHelper::getProperty(SalesChannelContext::class, 'shippingMethod');
        $prop->setValue($this->salesChannelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->first();
        static::assertNotNull($delivery);
        static::assertSame(1, $delivery->getShippingCosts()->getQuantity());
        static::assertSame(15.0, $delivery->getShippingCosts()->getUnitPrice());
        static::assertSame(15.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testReplaceBillingAddress(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create a new address for the existing customer

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getAddresses());

        $orderAddressId = $order->getAddresses()->first()?->getId();
        static::assertIsString($orderAddressId);

        $firstName = 'Replace first name';
        $lastName = 'Replace last name';
        $street = 'Replace street';
        $city = 'Replace city';
        $zipcode = '98765';

        $customerAddressId = $this->addAddressToCustomer(
            $this->customerId,
            $firstName,
            $lastName,
            $street,
            $city,
            $zipcode
        );

        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order-address/%s/customer-address/%s',
                $orderAddressId,
                $customerAddressId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getAddresses());
        $orderAddress = $order->getAddresses()->first();
        static::assertNotNull($orderAddress);

        static::assertSame($orderAddressId, $orderAddress->getId());
        static::assertSame($firstName, $orderAddress->getFirstName());
        static::assertSame($lastName, $orderAddress->getLastName());
        static::assertSame($street, $orderAddress->getStreet());
        static::assertSame($city, $orderAddress->getCity());
        static::assertSame($zipcode, $orderAddress->getZipcode());
    }

    public function testRecalculationControllerWithEmptyLineItems(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $order = $this->persistCart($cart);

        $orderId = $order['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $versionContext)->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame($order->getLineItems()->count(), 2);

        // delete all line items
        $ids = $order->getLineItems()->fmap(fn (OrderLineItemEntity $lineItem) => ['id' => $lineItem->getId()]);
        static::getContainer()->get('order_line_item.repository')->delete(array_values($ids), $versionContext);

        $order = $this->orderRepository->search($criteria, $versionContext)->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame($order->getLineItems()->count(), 0);

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    protected function getValidCountryIdWithTaxes(): string
    {
        $countryId = $this->getValidCountryId();

        $data = [
            'id' => $countryId,
            'iso' => 'XX',
            'iso3' => 'XXX',
            'active' => true,
            'shippingAvailable' => true,
            'taxFree' => false,
            'position' => 10,
            'displayStateInRegistration' => false,
            'forceStateInRegistration' => false,
            'translations' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => 'Takatuka',
                ],
            ],
        ];

        static::getContainer()->get('country.repository')->upsert(
            [$data],
            $this->context
        );

        return $countryId;
    }

    private function resetPayloadProtection(Cart $cart): void
    {
        // remove delivery information from line items
        $payloadProtection = ReflectionHelper::getProperty(LineItem::class, 'payloadProtection');

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $payloadProtection->setValue($lineItem, []);
        }

        foreach ($cart->getDeliveries() as $delivery) {
            foreach ($delivery->getPositions() as $position) {
                $payloadProtection->setValue($position->getLineItem(), []);
                foreach ($position->getLineItem()->getChildren() as $lineItem) {
                    $payloadProtection->setValue($lineItem, []);
                }
            }
        }
    }

    private function resetDataTimestamps(LineItemCollection $items): void
    {
        foreach ($items as $item) {
            $item->setDataTimestamp(null);
            $item->setDataContextHash(null);
            $this->resetDataTimestamps($item->getChildren());
        }
    }

    private function removeExtensions(Cart $cart): void
    {
        $this->removeLineItemsExtension($cart->getLineItems());

        foreach ($cart->getDeliveries() as $delivery) {
            $delivery->setExtensions([]);

            $delivery->getShippingMethod()->setExtensions([]);

            foreach ($delivery->getPositions() as $position) {
                $position->setExtensions([]);
                $this->removeLineItemsExtension(new LineItemCollection([$position->getLineItem()]));
            }
        }

        $cart->setExtensions([]);
        $cart->setData(null);
    }

    private function removeLineItemsExtension(LineItemCollection $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $lineItem->setExtensions([]);
            $this->removeLineItemsExtension($lineItem->getChildren());
        }
    }

    private function addAddressToCustomer(
        string $customerId,
        string $firstName,
        string $lastName,
        string $street,
        string $city,
        string $zipcode
    ): string {
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $street,
                    'zipcode' => $zipcode,
                    'city' => $city,
                ],
            ],
        ];

        static::getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $addressId;
    }

    private function createProduct(string $name, float $price, float $taxRate): string
    {
        $productId = Uuid::randomHex();

        $productNumber = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => $productNumber,
            'stock' => 5,
            'name' => $name,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price + ($price * $taxRate / 100), 'net' => $price, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => $taxRate],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        static::getContainer()->get('product.repository')->create([$data], $this->context);

        return $productId;
    }

    private function createPromotion(float $discountValue, ?string $code = null, string $type = PromotionDiscountEntity::TYPE_ABSOLUTE): string
    {
        $promotionId = Uuid::randomHex();

        $data = [
            'id' => $promotionId,
            'name' => 'auto promotion',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => false,
            'salesChannels' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => $type,
                    'value' => $discountValue,
                    'considerAdvancedRules' => false,
                ],
            ],
        ];

        if ($code) {
            $data['name'] = $code;
            $data['useCodes'] = true;
            $data['code'] = $code;
        }

        static::getContainer()->get('promotion.repository')->create([$data], $this->context);

        return $promotionId;
    }

    private function createShippingDiscount(float $discountValue, ?string $code = null): string
    {
        $promotionId = Uuid::randomHex();

        $data = [
            'id' => $promotionId,
            'name' => 'delivery promotion',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => false,
            'salesChannels' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_DELIVERY,
                    'type' => PromotionDiscountEntity::TYPE_PERCENTAGE,
                    'value' => $discountValue,
                    'considerAdvancedRules' => false,
                ],
            ],
        ];

        if ($code) {
            $data['name'] = $code;
            $data['useCodes'] = true;
            $data['code'] = $code;
        }

        static::getContainer()->get('promotion.repository')->create([$data], $this->context);

        return $promotionId;
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
                    'countryId' => $this->getValidCountryIdWithTaxes(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
    }

    private function getCustomerAddress(string $id): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId($id);
        $address->setCountryId($this->getValidCountryId());
        $address->setFirstName('Max');
        $address->setLastName('Mustermann');
        $address->setStreet('Musterstraße 1');
        $address->setZipcode('12345');
        $address->setCity('Musterstadt');

        return $address;
    }

    private function generateDemoCart(?string $productId1 = null, ?string $productId2 = null): Cart
    {
        $cart = new Cart(Uuid::randomHex());

        $cart = $this->addProduct($cart, $productId1 ?? Uuid::randomHex());

        $cart = $this->addProduct($cart, $productId2 ?? Uuid::randomHex(), [
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 5, 'name' => 'test'],
        ]);

        return $cart;
    }

    /**
     * @param array<string, array<string, int|string>|string> $options
     */
    private function addProduct(Cart $cart, string $id, array $options = []): Cart
    {
        $default = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'name' => 'test',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $product = array_replace_recursive($default, $options);

        static::getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);

        $lineItem = static::getContainer()->get(ProductLineItemFactory::class)
            ->create(['id' => $id, 'referencedId' => $id], $this->salesChannelContext);
        $lineItem->markUnmodified();

        $lineItem->assign(['uniqueIdentifier' => 'foo']);

        $cart->add($lineItem);

        $cart = static::getContainer()->get(Processor::class)
            ->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    /**
     * @return array{orderId: string, total: float, orderDateTime: \DateTimeInterface, stateId: string}
     */
    private function persistCart(Cart $cart, ?string $languageId = null): array
    {
        if ($languageId !== null) {
            $context = $this->salesChannelContext->getContext();
            $context->assign([
                'languageIdChain' => array_merge([$languageId], $context->getLanguageIdChain()),
            ]);
        }
        $orderId = static::getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);

        $criteria = new Criteria([$orderId]);
        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->get($orderId);
        static::assertNotNull($order);

        return [
            'orderId' => $orderId,
            'total' => $order->getPrice()->getTotalPrice(),
            'orderDateTime' => $order->getOrderDateTime(),
            'stateId' => $order->getStateId(),
        ];
    }

    private function createVersionedOrder(string $orderId): string
    {
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/version/order/%s',
                $orderId
            )
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $versionId = $content['versionId'];
        static::assertSame($orderId, $content['id']);
        static::assertSame('order', $content['entity']);
        static::assertTrue(Uuid::isValid($versionId));

        return $versionId;
    }

    private function addProductToVersionedOrder(
        string $productName,
        float $productPrice,
        float $productTaxRate,
        string $orderId,
        string $versionId,
        float $oldTotal
    ): string {
        $productId = $this->createProduct($productName, $productPrice, $productTaxRate);

        // add product to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/product/%s',
                $orderId,
                $productId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/recalculate',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());

        $product = $order->getLineItems()->firstWhere(
            static fn (OrderLineItemEntity $item) => $item->getIdentifier() === $productId,
        );

        static::assertNotNull($product);
        static::assertNotNull($product->getPrice());
        $productPriceInclTax = 10 + ($productPrice * $productTaxRate / 100);
        static::assertSame($product->getPrice()->getUnitPrice(), $productPriceInclTax);
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);

        static::assertSame($oldTotal + $productPriceInclTax, $order->getAmountTotal());

        return $productId;
    }

    private function addCustomLineItemToVersionedOrder(string $orderId, string $versionId, float $oldTotal, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $identifier = Uuid::randomHex();
        $data = [
            'identifier' => $identifier,
            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
            'quantity' => 10,
            'label' => 'example label',
            'description' => 'example description',
            'priceDefinition' => [
                'price' => 27.99,
                'quantity' => 10,
                'isCalculated' => false,
                'precision' => 2,
                'taxRules' => [
                    [
                        'taxRate' => 19,
                        'percentage' => 100,
                    ],
                ],
            ],
        ];

        // add product to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/lineItem',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            (string) json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());

        $customLineItem = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $identifier) {
                $customLineItem = $lineItem;
            }
        }

        static::assertNotNull($customLineItem);
        static::assertNotNull($customLineItem->getPrice());
        static::assertSame($customLineItem->getPrice()->getUnitPrice(), 33.31);
        static::assertSame($customLineItem->getPrice()->getQuantity(), 10);
        static::assertSame($customLineItem->getPrice()->getTotalPrice(), 333.1);
        $taxRule = $customLineItem->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), 19.0);
        static::assertSame($taxRule->getPercentage(), 100.0);
        $calculatedTaxes = $customLineItem->getPrice()->getCalculatedTaxes()->first();
        static::assertNotNull($calculatedTaxes);
        static::assertSame($calculatedTaxes->getPrice(), 333.1);
        static::assertSame($calculatedTaxes->getTaxRate(), 19.0);
        static::assertSame($calculatedTaxes->getTax(), 53.18);

        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($customLineItem->getPrice()->getTotalPrice() + $oldTotal, $order->getAmountTotal());
        static::assertSame($stateId, $order->getStateId());
    }

    private function addCreditItemToVersionedOrder(string $orderId, string $versionId, float $oldTotal, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->orderRepository;

        $identifier = Uuid::randomHex();
        $creditAmount = -10.0;
        $data = [
            'identifier' => $identifier,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'quantity' => 1,
            'label' => 'awesome credit',
            'description' => 'schubbidu',
            'priceDefinition' => [
                'price' => $creditAmount,
                'quantity' => 1,
                'isCalculated' => false,
                'precision' => 2,
            ],
        ];

        // add credit item to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/creditItem',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            (string) json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame($oldTotal + $creditAmount, $order->getAmountTotal());

        $creditItem = $order->getLineItems()->filterByProperty('identifier', $identifier)->first();
        static::assertNotNull($creditItem);
        $price = $creditItem->getPrice();
        static::assertNotNull($price);

        static::assertSame($creditAmount, $price->getTotalPrice());
        $taxRules = $price->getCalculatedTaxes();
        static::assertCount(2, $taxRules);
        static::assertArrayHasKey(19, $taxRules->getElements());
        static::assertArrayHasKey(5, $taxRules->getElements());
        $tax19 = $taxRules->getElements()[19];
        static::assertSame(19.0, $tax19->getTaxRate());
        $tax5 = $taxRules->getElements()[5];
        static::assertSame(5.0, $tax5->getTaxRate());

        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($creditAmount, $tax19->getPrice() + $tax5->getPrice());
        static::assertSame($stateId, $order->getStateId());
    }

    private function addPromotionItemToVersionedOrder(string $orderId, string $versionId, string $code, \DateTimeInterface $orderDateTime, string $stateId): OrderEntity
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'code' => $code,
        ];

        // add promotion item to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/promotion-item',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            (string) json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $promotionItem = $order->getLineItems()->filterByProperty('referencedId', $code)->first();

        static::assertNotNull($promotionItem);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame($errors[0]['message'], 'Discount GET5 has been added');
        static::assertSame($stateId, $order->getStateId());

        return $order;
    }

    /**
     * @return array{0: OrderEntity, 1: array<mixed>}
     */
    private function applyAutomaticPromotions(string $orderId, string $versionId, ?string $promotionId): array
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/applyAutomaticPromotions',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            (string) json_encode($data)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());

        $promotionItem = $order->getLineItems()->filterByType('promotion')->first();
        if ($promotionId) {
            static::assertNotNull($promotionItem);
            static::assertNotNull($promotionItem->getPayload());
            static::assertSame($promotionItem->getPayload()['promotionId'], $promotionId);
        } else {
            static::assertNull($promotionItem);
        }

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$order, $content];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    private function toggleAutomaticPromotions(string $orderId, string $versionId, string $promotionId, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/toggleAutomaticPromotions',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            (string) json_encode($data)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $promotionItem = $order->getLineItems()->filterByProperty('type', 'promotion')->first();

        static::assertNotNull($promotionItem);
        $payload = $promotionItem->getPayload();
        static::assertNotNull($payload);

        static::assertSame($payload['promotionId'], $promotionId);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame($errors[0]['message'], 'Discount auto promotion has been added');
        static::assertSame($stateId, $order->getStateId());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    private function toggleAutomaticPromotionsForDelivery(string $orderId, string $versionId, string $promotionId, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/toggleAutomaticPromotions',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            (string) json_encode($data)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getDeliveries());
        static::assertCount(2, $order->getDeliveries());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $firstDelivery = $order->getDeliveries()->first();
        $secondDelivery = $order->getDeliveries()->last();

        static::assertInstanceOf(OrderDeliveryEntity::class, $firstDelivery);
        static::assertInstanceOf(OrderDeliveryEntity::class, $secondDelivery);

        static::assertSame($firstDelivery->getShippingCosts()->getTotalPrice(), 5.0);
        static::assertSame($secondDelivery->getShippingCosts()->getTotalPrice(), -5.0);
    }

    /**
     * @return array<string, int|string>
     */
    private function createDeliveryTime(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }

    private function createShippingMethod(string $priceRuleId): string
    {
        $shippingMethodId = Uuid::randomHex();
        $deliveryTimeData = $this->createDeliveryTime();

        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $taxId = Uuid::randomHex();

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method',
            'technicalName' => 'shipping_test',
            'bindShippingfree' => false,
            'active' => true,
            'deliveryTime' => $deliveryTimeData,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.00,
                            'gross' => 10.00,
                            'linked' => false,
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 8.00,
                            'gross' => 8.00,
                            'linked' => false,
                        ],
                    ],
                    'calculationRule' => [
                        'name' => 'check',
                        'priority' => 10,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
            'taxId' => $taxId,
            'tax' => [
                'id' => $taxId,
                'taxRate' => 19,
                'name' => 'test',
            ],
        ];

        $this->shippingMethodRepository->create([$data], $this->context);

        return $shippingMethodId;
    }

    private function addSecondPriceRuleToShippingMethod(string $priceRuleId, string $shippingMethodId): ShippingMethodEntity
    {
        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method 2',
            'technicalName' => 'shipping_test2',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTime(),
            'active' => true,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 15.00,
                            'gross' => 15.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 0,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 20.00,
                            'gross' => 20.00,
                            'linked' => false,
                        ],
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $this->shippingMethodRepository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('priceRules');

        $shippingMethod = $this->shippingMethodRepository->search($criteria, $this->context)->get($shippingMethodId);
        static::assertNotNull($shippingMethod);

        return $shippingMethod;
    }

    private function addSecondShippingMethodPriceRule(string $priceRuleId, string $shippingMethodId): ShippingMethodEntity
    {
        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method 3',
            'technicalName' => 'shipping_test3',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTime(),
            'active' => true,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 15.00,
                            'gross' => 15.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'quantityEnd' => 9,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.00,
                            'gross' => 10.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 10,
                    'quantityEnd' => 20,
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $this->shippingMethodRepository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');

        $shippingMethod = $this->shippingMethodRepository->search($criteria, $this->context)->get($shippingMethodId);
        static::assertNotNull($shippingMethod);

        return $shippingMethod;
    }

    private function createTwoConditionsWithDifferentQuantities(string $priceRuleId, string $shippingMethodId, int $calculation): ShippingMethodEntity
    {
        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method 4',
            'technicalName' => 'shipping_test4',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTime(),
            'active' => true,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 15.00,
                            'gross' => 15.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => $calculation,
                    'quantityStart' => 0,
                    'quantityEnd' => 70,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 9.99,
                            'gross' => 9.99,
                            'linked' => false,
                        ],
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => $calculation,
                    'quantityStart' => 71,
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $this->shippingMethodRepository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('priceRules');
        $criteria->addAssociation('deliveryTime');

        $shippingMethod = $this->shippingMethodRepository->search($criteria, $this->context)->get($shippingMethodId);
        static::assertNotNull($shippingMethod);

        return $shippingMethod;
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => TestPaymentHandler::class,
            'name' => 'Payment',
            'technicalName' => 'payment_test',
            'active' => true,
            'position' => 0,
            'availabilityRule' => [
                'id' => $ruleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
            'salesChannels' => [
                [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ],
        ];

        static::getContainer()->get('payment_method.repository')->create([$data], $this->context);

        return $paymentMethodId;
    }
}
