<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\PaymentHandler\SyncTestPaymentHandler;
use Shopware\Core\Test\Stub\Rule\TrueRule;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('slow')]
#[Group('skip-paratest')]
class RecalculationServiceTest extends TestCase
{
    use AdminApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    protected SalesChannelContext $salesChannelContext;

    protected Context $context;

    protected string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);
        $this->addCountriesToSalesChannel([$this->getValidCountryIdWithTaxes()]);
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
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

        $cart = $this->getContainer()->get(Processor::class)
            ->process($cart, $this->salesChannelContext, new CartBehavior());

        $orderId = $this->persistCart($cart)['orderId'];

        $deliveryCriteria = new Criteria();
        $deliveryCriteria->addAssociation('positions');

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, $this->context)
            ->get($orderId);
        static::assertNotNull($order->getNestedLineItems());

        // check lineItem sorting
        $idx = 0;
        foreach ($order->getNestedLineItems() as $lineItem) {
            if ($idx === 0) {
                static::assertEquals($parentProductId, $lineItem->getReferencedId());
            } else {
                static::assertEquals($rootProductId, $lineItem->getReferencedId());
            }
            ++$idx;
        }

        $convertedCart = $this->getContainer()->get(OrderConverter::class)
            ->convertToCart($order, $this->context);

        // check token
        static::assertNotEquals($cart->getToken(), $convertedCart->getToken());
        static::assertTrue(Uuid::isValid($convertedCart->getToken()));

        // check lineItem sorting
        $idx = 0;
        foreach ($convertedCart->getLineItems() as $lineItem) {
            if ($idx === 0) {
                static::assertEquals($parentProductId, $lineItem->getId());
            } else {
                static::assertEquals($rootProductId, $lineItem->getId());
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
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $versionContext)->get($orderId);

        static::assertNotNull($order->getOrderCustomer());

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $versionContext)->get($orderId);

        static::assertEquals($this->getDeDeLanguageId(), $order->getLanguageId());
    }

    public function testRecalculationWithDeletedCustomer(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        /** @var EntityRepository $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customerRepository->delete([['id' => $this->customerId]], $this->context);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $versionContext)->get($orderId);

        static::assertNotNull($order->getOrderCustomer());

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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

        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        /** @var EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository */
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        $delivery = $deliveries->getEntities()->first();
        static::assertNotNull($delivery);
        $newShippingCosts = $delivery->getShippingCosts();

        $firstTax = $newShippingCosts->getCalculatedTaxes()->first();
        $lastTax = $newShippingCosts->getCalculatedTaxes()->last();

        // tax is now mixed
        static::assertEquals(2, $newShippingCosts->getCalculatedTaxes()->count());
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
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
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
        /** @var TaxRule $taxRule */
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);

        static::assertNotNull($order->getAmountTotal());
        static::assertEquals($oldTotal + $productPriceInclTax, $order->getAmountTotal());
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

        $this->getContainer()->get('order.repository')
            ->merge($versionId, Context::createDefaultContext());

        $stocks = $this->getContainer()->get(Connection::class)
            ->fetchAssociative('SELECT stock, available_stock FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($productId)]);

        static::assertIsArray($stocks);

        static::assertEquals(4, $stocks['stock']);
        static::assertEquals(4, $stocks['available_stock']);
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
            sprintf(
                '/api/_action/version/merge/%s/%s',
                $this->getContainer()->get(OrderDefinition::class)->getEntityName(),
                $versionId
            )
        );
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read merged order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context)->get($orderId);
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
        /** @var TaxRule $taxRule */
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);
        static::assertNotNull($order->getOrderDateTime());
        static::assertEquals($order->getOrderDateTime(), $orderDateTime);
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
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        static::assertEquals(1, $deliveries->count());

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();
        $shippingCosts = $delivery->getShippingCosts();

        static::assertSame(1, $shippingCosts->getQuantity());
        static::assertSame(10.0, $shippingCosts->getUnitPrice());
        static::assertSame(10.0, $shippingCosts->getTotalPrice());
        static::assertEquals(2, $shippingCosts->getCalculatedTaxes()->count());

        // change shipping costs
        $newShippingCosts = new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection());

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();

        $payload = [
            'id' => $delivery->getId(),
            'shippingCosts' => $newShippingCosts,
        ];

        $orderDeliveryRepository->upsert([$payload], $versionContext);

        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();
        $newShippingCosts = $delivery->getShippingCosts();

        static::assertSame(1, $newShippingCosts->getQuantity());
        static::assertSame(5.0, $newShippingCosts->getUnitPrice());
        static::assertSame(5.0, $newShippingCosts->getTotalPrice());

        /** @var CalculatedTax|null $firstTax */
        $firstTax = $newShippingCosts->getCalculatedTaxes()->first();
        /** @var CalculatedTax|null $lastTax */
        $lastTax = $newShippingCosts->getCalculatedTaxes()->last();

        // tax is now mixed
        static::assertEquals(2, $newShippingCosts->getCalculatedTaxes()->count());
        static::assertNotNull($firstTax);
        static::assertSame(19.0, $firstTax->getTaxRate());
        static::assertNotNull($lastTax);
        static::assertSame(5.0, $lastTax->getTaxRate());
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

        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, $this->context)
            ->get($orderId);

        static::assertSame(224.07, $order->getPrice()->getNetPrice());
        static::assertSame(249.98, $order->getPrice()->getTotalPrice());
        static::assertSame(239.98, $order->getPrice()->getPositionPrice());

        $this->getContainer()->get('product.repository')->update([['id' => $inactiveProductId, 'active' => false]], $this->context);

        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, $this->context)
            ->get($orderId);

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

        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();
        $shippingCosts = $delivery->getShippingCosts();

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
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $delivery->getShippingMethod();

        /** @var ShippingMethodPriceEntity $firstPriceRule */
        $firstPriceRule = $shippingMethod->getPrices()->first();
        /** @var ShippingMethodPriceEntity $secondPriceRule */
        $secondPriceRule = $shippingMethod->getPrices()->last();

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

        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();

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
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();
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
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($criteria, $versionContext);

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();
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

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getAddresses());

        /** @var OrderAddressEntity $address */
        $address = $order->getAddresses()->first();
        $orderAddressId = $address->getId();
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
            sprintf(
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

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getAddresses());
        /** @var OrderAddressEntity $orderAddress */
        $orderAddress = $order->getAddresses()->first();

        static::assertSame($orderAddressId, $orderAddress->getId());
        static::assertSame($firstName, $orderAddress->getFirstName());
        static::assertSame($lastName, $orderAddress->getLastName());
        static::assertSame($street, $orderAddress->getStreet());
        static::assertSame($city, $orderAddress->getCity());
        static::assertSame($zipcode, $orderAddress->getZipcode());
    }

    protected function getValidCountryIdWithTaxes(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('country.repository');

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

        $repository->upsert(
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

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

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
        $this->getContainer()->get('product.repository')->create([$data], $this->context);

        return $productId;
    }

    private function createPromotion(float $discountValue, ?string $code = null): string
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
                    'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
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

        $this->getContainer()->get('promotion.repository')->create([$data], $this->context);

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
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
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

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
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

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);

        $lineItem = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create(['id' => $id, 'referencedId' => $id], $this->salesChannelContext);
        $lineItem->markUnmodified();

        $lineItem->assign(['uniqueIdentifier' => 'foo']);

        $cart->add($lineItem);

        $cart = $this->getContainer()->get(Processor::class)
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
        $orderId = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);

        $criteria = new Criteria([$orderId]);
        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->salesChannelContext->getContext())->get($orderId);

        return ['orderId' => $orderId, 'total' => $order->getPrice()->getTotalPrice(), 'orderDateTime' => $order->getOrderDateTime(), 'stateId' => $order->getStateId()];
    }

    private function createVersionedOrder(string $orderId): string
    {
        $this->getBrowser()->request(
            'POST',
            sprintf(
                '/api/_action/version/order/%s',
                $orderId
            )
        );
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $versionId = $content['versionId'];
        static::assertEquals($orderId, $content['id']);
        static::assertEquals('order', $content['entity']);
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
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
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
        /** @var TaxRule $taxRule */
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);

        static::assertEquals($oldTotal + $productPriceInclTax, $order->getAmountTotal());

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
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
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
        /** @var TaxRule $taxRule */
        $taxRule = $customLineItem->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), 19.0);
        static::assertSame($taxRule->getPercentage(), 100.0);
        /** @var CalculatedTax $calculatedTaxes */
        $calculatedTaxes = $customLineItem->getPrice()->getCalculatedTaxes()->first();
        static::assertSame($calculatedTaxes->getPrice(), 333.1);
        static::assertSame($calculatedTaxes->getTaxRate(), 19.0);
        static::assertSame($calculatedTaxes->getTax(), 53.18);

        static::assertEquals($order->getOrderDateTime(), $orderDateTime);
        static::assertSame($customLineItem->getPrice()->getTotalPrice() + $oldTotal, $order->getAmountTotal());
        static::assertSame($stateId, $order->getStateId());
    }

    private function addCreditItemToVersionedOrder(string $orderId, string $versionId, float $oldTotal, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->getContainer()->get('order.repository');

        $identifier = Uuid::randomHex();
        $creditAmount = -10;
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
            sprintf(
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

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity $order */
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertEquals($oldTotal + $creditAmount, $order->getAmountTotal());

        /** @var OrderLineItemEntity $creditItem */
        $creditItem = $order->getLineItems()->filterByProperty('identifier', $identifier)->first();
        /** @var CalculatedPrice $price */
        $price = $creditItem->getPrice();

        static::assertEquals($creditAmount, $price->getTotalPrice());
        $taxRules = $price->getCalculatedTaxes();
        static::assertCount(2, $taxRules);
        static::assertArrayHasKey(19, $taxRules->getElements());
        static::assertArrayHasKey(5, $taxRules->getElements());
        /** @var CalculatedTax $tax19 */
        $tax19 = $taxRules->getElements()[19];
        static::assertEquals(19, $tax19->getTaxRate());
        /** @var CalculatedTax $tax5 */
        $tax5 = $taxRules->getElements()[5];
        static::assertEquals(5, $tax5->getTaxRate());

        static::assertEquals($order->getOrderDateTime(), $orderDateTime);
        static::assertEquals($creditAmount, $tax19->getPrice() + $tax5->getPrice());
        static::assertSame($stateId, $order->getStateId());
    }

    private function addPromotionItemToVersionedOrder(string $orderId, string $versionId, string $code, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->getContainer()->get('order.repository');

        $data = [
            'code' => $code,
        ];

        // add promotion item to order
        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity $order */
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertEquals($order->getOrderDateTime(), $orderDateTime);

        $promotionItem = $order->getLineItems()->filterByProperty('referencedId', $code)->first();

        static::assertNotNull($promotionItem);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertEquals($errors[0]['message'], 'Discount GET5 has been added');
        static::assertSame($stateId, $order->getStateId());
    }

    private function toggleAutomaticPromotions(string $orderId, string $versionId, string $promotionId, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->getContainer()->get('order.repository');

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->request(
            'POST',
            sprintf(
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

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        /** @var OrderEntity $order */
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertEquals($order->getOrderDateTime(), $orderDateTime);

        /** @var LineItem|null $promotionItem */
        $promotionItem = $order->getLineItems()->filterByProperty('type', 'promotion')->first();

        static::assertNotNull($promotionItem);

        static::assertEquals($promotionItem->getPayload()['promotionId'], $promotionId);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertEquals($errors[0]['message'], 'Discount auto promotion has been added');
        static::assertSame($stateId, $order->getStateId());
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
        $repository = $this->getContainer()->get('shipping_method.repository');
        $deliveryTimeData = $this->createDeliveryTime();

        $ruleRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

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
        ];

        $repository->create([$data], $this->context);

        return $shippingMethodId;
    }

    private function addSecondPriceRuleToShippingMethod(string $priceRuleId, string $shippingMethodId): ShippingMethodEntity
    {
        $repository = $this->getContainer()->get('shipping_method.repository');
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

        $repository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('priceRules');

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $repository->search($criteria, $this->context)->get($shippingMethodId);

        return $shippingMethod;
    }

    private function addSecondShippingMethodPriceRule(string $priceRuleId, string $shippingMethodId): ShippingMethodEntity
    {
        $repository = $this->getContainer()->get('shipping_method.repository');
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

        $repository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $repository->search($criteria, $this->context)->get($shippingMethodId);

        return $shippingMethod;
    }

    private function createTwoConditionsWithDifferentQuantities(string $priceRuleId, string $shippingMethodId, int $calculation): ShippingMethodEntity
    {
        $repository = $this->getContainer()->get('shipping_method.repository');

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

        $repository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('priceRules');
        $criteria->addAssociation('deliveryTime');

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $repository->search($criteria, $this->context)->get($shippingMethodId);

        return $shippingMethod;
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $repository = $this->getContainer()->get('payment_method.repository');

        $ruleRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => SyncTestPaymentHandler::class,
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

        $repository->create([$data], $this->context);

        return $paymentMethodId;
    }
}
