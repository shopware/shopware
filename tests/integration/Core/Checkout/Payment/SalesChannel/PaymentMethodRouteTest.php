<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\Hook\PaymentMethodRouteHook;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class PaymentMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
                ['id' => $this->ids->get('payment2')],
                ['id' => $this->ids->get('payment3')],
            ],
        ]);
    }

    public function testLoading(): void
    {
        $this->browser->request('POST', '/store-api/payment-method');

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $ids = array_column($response['elements'], 'id');

        static::assertSame(3, $response['total']);
        static::assertContains($this->ids->get('payment'), $ids);
        static::assertContains($this->ids->get('payment2'), $ids);
        static::assertContains($this->ids->get('payment3'), $ids);

        $traces = $this->browser->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(PaymentMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/payment-method',
            [
                'includes' => [
                    'payment_method' => [
                        'name',
                    ],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    public static function httpMethodProvider(): \Generator
    {
        yield 'GET with query parameters' => ['GET'];
        yield 'POST with a JSON body' => ['POST'];
    }

    #[DataProvider('httpMethodProvider')]
    public function testOnlyAvailableIsEvaluatedForTheSessionWithoutOrderId(string $httpMethod): void
    {
        $this->createRules();
        $this->createAfterOrderPaymentMethod('order-payment', availabilityRuleId: $this->ids->get('order-rule'));
        $this->createAfterOrderPaymentMethod('session-payment', availabilityRuleId: $this->ids->get('session-rule'));
        $this->login($this->browser);

        $paymentMethodIds = $this->loadPaymentMethodIds($httpMethod, ['onlyAvailable' => true]);

        static::assertNotContains($this->ids->get('order-payment'), $paymentMethodIds);
        static::assertContains($this->ids->get('session-payment'), $paymentMethodIds);
    }

    #[DataProvider('httpMethodProvider')]
    public function testOnlyAvailableWithOrderIdUsesTheRulesStoredOnTheOrder(string $httpMethod): void
    {
        $this->createRules();
        $this->createAfterOrderPaymentMethod('order-payment', availabilityRuleId: $this->ids->get('order-rule'));
        $this->createAfterOrderPaymentMethod('session-payment', availabilityRuleId: $this->ids->get('session-rule'));
        $orderId = $this->createOrder($this->login($this->browser), ruleIds: [$this->ids->get('order-rule')]);

        $paymentMethodIds = $this->loadPaymentMethodIds($httpMethod, ['onlyAvailable' => true, 'orderId' => $orderId]);

        static::assertContains($this->ids->get('order-payment'), $paymentMethodIds);
        static::assertNotContains($this->ids->get('session-payment'), $paymentMethodIds);
    }

    public function testOrderOfAnotherCustomerIsNotFoundLikeAnUnknownOrder(): void
    {
        $this->login($this->browser);
        $foreignOrderId = $this->createOrder($this->createCustomer());

        $this->browser->request('GET', '/store-api/payment-method?orderId=' . $foreignOrderId);
        $foreignOrderResponse = $this->browser->getResponse();

        $this->browser->request('GET', '/store-api/payment-method?orderId=' . Uuid::randomHex());
        $unknownOrderResponse = $this->browser->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $foreignOrderResponse->getStatusCode());
        static::assertSame(OrderException::ORDER_ORDER_NOT_FOUND_CODE, $this->getErrorCode($foreignOrderResponse));
        static::assertSame(Response::HTTP_NOT_FOUND, $unknownOrderResponse->getStatusCode());
        static::assertSame($this->getErrorCode($foreignOrderResponse), $this->getErrorCode($unknownOrderResponse));
    }

    public function testMalformedOrderIdIsRejected(): void
    {
        $this->login($this->browser);

        $this->browser->request('GET', '/store-api/payment-method?orderId=foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(OrderException::CHECKOUT_INVALID_UUID, $this->getErrorCode($this->browser->getResponse()));
    }

    public function testOrderIdRequiresALoggedInCustomer(): void
    {
        $orderId = $this->createOrder($this->createCustomer());

        $this->browser->request('GET', '/store-api/payment-method?orderId=' . $orderId);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testOrderBasedStateStaysRequestLocal(): void
    {
        $customerId = $this->login($this->browser);
        $orderId = $this->createOrder($customerId);
        $contextToken = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $connection = static::getContainer()->get(Connection::class);
        $cartCount = $connection->fetchOne('SELECT COUNT(*) FROM cart');

        $this->browser->request('GET', '/store-api/payment-method?orderId=' . $orderId);
        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        if ($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            static::assertSame($contextToken, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        }
        static::assertSame($cartCount, $connection->fetchOne('SELECT COUNT(*) FROM cart'));

        $this->browser->request('GET', '/store-api/context');
        $context = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($customerId, $context['customer']['id']);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<string>
     */
    private function loadPaymentMethodIds(string $httpMethod, array $parameters): array
    {
        if ($httpMethod === 'GET') {
            $this->browser->request('GET', '/store-api/payment-method?' . http_build_query($parameters));
        } else {
            $this->browser->request(
                'POST',
                '/store-api/payment-method',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode($parameters, \JSON_THROW_ON_ERROR),
            );
        }

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return array_column($content['elements'], 'id');
    }

    private function getErrorCode(Response $response): string
    {
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $content['errors'][0]['code'];
    }

    private function createRules(): void
    {
        static::getContainer()->get('rule.repository')->create([
            [
                'id' => $this->ids->create('order-rule'),
                'name' => 'Cart amount the session cart never reaches',
                'priority' => 1,
                'conditions' => [[
                    'type' => CartAmountRule::RULE_NAME,
                    'value' => ['operator' => Rule::OPERATOR_GTE, 'amount' => 1_000_000],
                ]],
            ],
            [
                'id' => $this->ids->create('session-rule'),
                'name' => 'Always valid',
                'priority' => 1,
                'conditions' => [['type' => AlwaysValidRule::RULE_NAME]],
            ],
        ], Context::createDefaultContext());
    }

    private function createAfterOrderPaymentMethod(string $key, string $availabilityRuleId): void
    {
        static::getContainer()->get('payment_method.repository')->create([[
            'id' => $this->ids->create($key),
            'name' => $key,
            'technicalName' => 'payment_' . $key,
            'active' => true,
            'afterOrderEnabled' => true,
            'handlerIdentifier' => TestPaymentHandler::class,
            'availabilityRuleId' => $availabilityRuleId,
            'salesChannels' => [['id' => $this->ids->get('sales-channel')]],
        ]], Context::createDefaultContext());
    }

    /**
     * @param list<string> $ruleIds
     */
    private function createOrder(string $customerId, array $ruleIds = []): string
    {
        $orderId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $price = new CalculatedPrice(unitPrice: 10, totalPrice: 10, calculatedTaxes: new CalculatedTaxCollection(), taxRules: new TaxRuleCollection());
        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];

        static::getContainer()->get('order.repository')->create([[
            'id' => $orderId,
            'orderNumber' => '10000',
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'salesChannelId' => $this->ids->get('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'stateId' => $this->getStateMachineState(),
            'ruleIds' => $ruleIds,
            'price' => new CartPrice(
                netPrice: 10,
                totalPrice: 10,
                positionPrice: 10,
                calculatedTaxes: new CalculatedTaxCollection(),
                taxRules: new TaxRuleCollection(),
                taxStatus: CartPrice::TAX_STATE_NET,
            ),
            'shippingCosts' => new CalculatedPrice(unitPrice: 0, totalPrice: 0, calculatedTaxes: new CalculatedTaxCollection(), taxRules: new TaxRuleCollection()),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'billingAddressId' => $billingAddressId,
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
            'lineItems' => [[
                'identifier' => 'custom-item',
                'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                'label' => 'Custom item',
                'quantity' => 1,
                'price' => $price,
                'priceDefinition' => new QuantityPriceDefinition(price: 10, taxRules: new TaxRuleCollection()),
            ]],
            'deliveries' => [],
            'transactions' => [[
                'paymentMethodId' => $this->ids->get('payment'),
                'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                'amount' => $price,
            ]],
        ]], Context::createDefaultContext());

        return $orderId;
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => 'Payment 1',
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51',
                                'toDate' => '2099-06-07T11:37:51',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => 'Payment 2',
                'technicalName' => 'payment_test2',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51',
                                'toDate' => '2099-06-07T11:37:51',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('payment3'),
                'name' => 'Payment 3',
                'technicalName' => 'payment_test3',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51',
                                'toDate' => '2000-06-07T11:37:51',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('payment_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
