<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestFailedPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const UUID_LENGTH = 32;
    private const PRODUCT_PRICE = 15.99;
    private const CUSTOMER_NAME = 'Tester';
    private const TEST_AFFILIATE_CODE = 'testAffiliateCode';
    private const TEST_CAMPAIGN_CODE = 'testCampaignCode';

    /**
     * @var string
     */
    private $failedPaymentMethodId;

    /**
     * @dataProvider customerComments
     *
     * @param string|float|int|bool|null $customerComment
     */
    public function testOrderCustomerComment($customerComment, ?string $savedCustomerComment): void
    {
        $order = $this->performOrder($customerComment);
        static::assertSame($savedCustomerComment, $order->getCustomerComment());
    }

    public function customerComments(): array
    {
        return [
            ["  Hello, \nthis is a customer comment!  ", "Hello, \nthis is a customer comment!"],
            ['<script>alert("hello")</script>', 'alert("hello")'],
            ['<h1>Hello</h1><br><br>This is a Test! ', 'HelloThis is a Test!'],
            ['  ', null],
            ['', null],
            [1.2, '1.2'],
            [12, '12'],
            [true, '1'],
            [false, null],
            [null, null],
        ];
    }

    public function testOrder(): void
    {
        $order = $this->performOrder('');

        static::assertSame(self::PRODUCT_PRICE, $order->getPrice()->getTotalPrice());
        static::assertSame(self::CUSTOMER_NAME, $order->getOrderCustomer()->getLastName());
    }

    public function testOrderWithInactivePaymentMethod(): void
    {
        $this->expectException(PaymentMethodNotAvailableException::class);

        $this->performOrder('', true);
    }

    public function testOrderWithFailedPaymentMethod(): void
    {
        $this->createFailedPaymentMethodData();

        $contextToken = Uuid::randomHex();

        $this->fillCart($contextToken, false, true);

        $requestDataBag = $this->createRequestDataBag('');
        $salesChannelContext = $this->createSalesChannelContext($contextToken, true);
        $request = $this->createRequest();

        /** @var RedirectResponse|Response $response */
        $response = $this->getContainer()->get(CheckoutController::class)->order($requestDataBag, $salesChannelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertStringContainsString('/checkout/finish', $response->getTargetUrl(), 'Target Url does not point to /checkout/finish');
        static::assertStringContainsString('paymentFailed=1', $response->getTargetUrl(), 'Target Url does not contain paymentFailed=1 as query parameter');
        static::assertStringContainsString('changedPayment=0', $response->getTargetUrl(), 'Target Url does not contain changedPayment=0 as query parameter');
    }

    public function testAffiliateOrder(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, self::TEST_AFFILIATE_CODE);
        $request->getSession()->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, self::TEST_CAMPAIGN_CODE);

        $order = $this->performOrder('', false, $request);

        static::assertSame(self::TEST_AFFILIATE_CODE, $order->getAffiliateCode());
        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
    }

    /**
     * @param string|float|int|bool|null $customerComment
     */
    private function performOrder($customerComment, ?bool $useInactivePaymentMethod = false, ?Request $request = null): OrderEntity
    {
        $contextToken = Uuid::randomHex();

        $this->fillCart($contextToken, $useInactivePaymentMethod);

        $requestDataBag = $this->createRequestDataBag($customerComment);
        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        if ($request === null) {
            $request = $this->createRequest();
        }

        /** @var RedirectResponse|Response $response */
        $response = $this->getContainer()->get(CheckoutController::class)->order($requestDataBag, $salesChannelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);

        $orderId = substr($response->getTargetUrl(), -self::UUID_LENGTH);

        /** @var EntityRepositoryInterface $orderRepo */
        $orderRepo = $this->getContainer()->get('order.repository');

        /** @var OrderEntity|null $order */
        $order = $orderRepo->search(new Criteria([$orderId]), Context::createDefaultContext())->first();

        static::assertNotNull($order);

        return $order;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $salutationId = $this->getValidSalutationId();
        $paymentMethodId = $this->getValidPaymentMethodId();

        $customer = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $customerId,
                    'firstName' => 'Test',
                    'lastName' => self::CUSTOMER_NAME,
                    'city' => 'Schöppingen',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'salutationId' => $salutationId,
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $customerId,
                'defaultPaymentMethodId' => $paymentMethodId,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not',
                'firstName' => 'Test',
                'lastName' => self::CUSTOMER_NAME,
                'salutationId' => $salutationId,
                'customerNumber' => '12345',
            ],
        ];

        $this->getContainer()->get('customer.repository')->create($customer, Context::createDefaultContext());

        return $customerId;
    }

    private function createProduct(): string
    {
        $productId = Uuid::randomHex();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => '123456789',
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => self::PRODUCT_PRICE, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $productId, 'name' => 'shopware AG'],
            'tax' => ['id' => $productId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        return $productId;
    }

    private function fillCart(string $contextToken, ?bool $useInactivePaymentMethod = false, ?bool $useFailedPaymentMethod = false): void
    {
        $cart = $this->getContainer()->get(CartService::class)->createNew($contextToken);

        $productId = $this->createProduct();
        $cart->add(new LineItem('lineItem1', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));

        if ($useInactivePaymentMethod) {
            $cart->setTransactions($this->createTransactionWithInactivePaymentMethod());

            return;
        }
        if ($useFailedPaymentMethod) {
            $cart->setTransactions($this->createTransactionWithFailedPaymentMethod());

            return;
        }
        $cart->setTransactions($this->createTransaction());
    }

    private function createTransaction(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->getValidPaymentMethodId()
            ),
        ]);
    }

    private function createTransactionWithInactivePaymentMethod(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->getInactivePaymentMethodId()
            ),
        ]);
    }

    private function createTransactionWithFailedPaymentMethod(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->failedPaymentMethodId
            ),
        ]);
    }

    /**
     * @param string|float|int|bool|null $customerComment
     */
    private function createRequestDataBag($customerComment): RequestDataBag
    {
        return new RequestDataBag(['tos' => true, OrderService::CUSTOMER_COMMENT_KEY => $customerComment]);
    }

    private function createSalesChannelContext(string $contextToken, ?bool $withFailedPaymentMethod = false): SalesChannelContext
    {
        $salesChannelData = [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
        ];
        if ($withFailedPaymentMethod === true) {
            $salesChannelData[SalesChannelContextService::PAYMENT_METHOD_ID] = $this->failedPaymentMethodId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            Defaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));

        return $request;
    }

    private function createFailedPaymentMethodData(): string
    {
        $paymentId = Uuid::randomHex();
        $data = [
            [
                'id' => $paymentId,
                'name' => SyncTestFailedPaymentHandler::class,
                'active' => true,
                'handlerIdentifier' => SyncTestFailedPaymentHandler::class,
                'salesChannels' => [
                    [
                        'id' => Defaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('payment_method.repository')
            ->create($data, Context::createDefaultContext());

        $this->failedPaymentMethodId = $paymentId;

        return $paymentId;
    }
}
