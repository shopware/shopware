<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Gateway\SalesChannel;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\App\GuzzleHistoryCollector;
use Shopware\Core\Test\Integration\App\TestAppServer;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class CheckoutGatewayRouteTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
            ],
        ]);

        $historyCollector = static::getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);
        $historyCollector->resetHistory();
        $mockHandler = static::getContainer()->get(MockHandler::class);
        static::assertInstanceOf(MockHandler::class, $mockHandler);
        $mockHandler->reset();
        $testServer = static::getContainer()->get(TestAppServer::class);
        static::assertInstanceOf(TestAppServer::class, $testServer);
        $testServer->reset();
    }

    public function testLoad(): void
    {
        $body = \json_encode([
            [
                'command' => 'add-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_new-test',
                ],
            ],
        ], flags: \JSON_THROW_ON_ERROR);

        $secret = \hash_hmac('sha256', $body, 'secret');

        $this->appendNewResponse(new Response(200, [RequestSigner::SHOPWARE_APP_SIGNATURE => $secret], $body));

        $this->browser->request('GET', '/store-api/checkout/gateway');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('payments', $response, 'Response has probably errors');
        static::assertIsArray($response['payments']);

        $payments = $response['payments'];

        static::assertCount(2, $payments);
        static::assertArrayHasKey('technicalName', $payments[0]);
        static::assertSame('payment_test', $payments[0]['technicalName']);
        static::assertArrayHasKey('technicalName', $payments[1]);
        static::assertSame('payment_new-test', $payments[1]['technicalName']);
    }

    public function testLoadWithHandlerError(): void
    {
        $body = \json_encode([
            [
                'command' => 'add-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'this-payment-method-does-not-exist',
                ],
            ],
        ], flags: \JSON_THROW_ON_ERROR);

        $secret = \hash_hmac('sha256', $body, 'secret');

        $this->appendNewResponse(new Response(200, [RequestSigner::SHOPWARE_APP_SIGNATURE => $secret], $body));

        $this->browser->request('GET', '/store-api/checkout/gateway');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertIsArray($response['errors']);

        $errors = $response['errors'];

        static::assertCount(1, $errors);
        static::assertArrayHasKey('code', $errors[0]);
        static::assertSame('CHECKOUT_GATEWAY__HANDLER_EXCEPTION', $errors[0]['code']);
        static::assertArrayHasKey('detail', $errors[0]);
        static::assertSame('Payment method "this-payment-method-does-not-exist" not found', $errors[0]['detail']);
    }

    public function testLoadWithMultipleCommands(): void
    {
        $body = \json_encode([
            [
                'command' => 'add-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_new-test',
                ],
            ],
            [
                'command' => 'remove-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_test',
                ],
            ],
        ], flags: \JSON_THROW_ON_ERROR);

        $secret = \hash_hmac('sha256', $body, 'secret');

        $this->appendNewResponse(new Response(200, [RequestSigner::SHOPWARE_APP_SIGNATURE => $secret], $body));

        $this->browser->request('GET', '/store-api/checkout/gateway');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('payments', $response, 'Response has probably errors');
        static::assertIsArray($response['payments']);

        $payments = $response['payments'];

        static::assertCount(1, $payments);

        static::assertArrayHasKey('technicalName', $payments[0]);
        static::assertSame('payment_new-test', $payments[0]['technicalName']);
    }

    public function testLoadWithOrderIdUsesTheRulesStoredOnTheOrder(): void
    {
        $this->createRules();
        $this->createAfterOrderPaymentMethod('order-payment', availabilityRuleId: $this->ids->get('order-rule'));
        $this->createAfterOrderPaymentMethod('session-payment', availabilityRuleId: $this->ids->get('session-rule'));
        $orderId = $this->createOrder($this->login($this->browser), ruleIds: [$this->ids->get('order-rule')]);

        $body = \json_encode([], flags: \JSON_THROW_ON_ERROR);

        $secret = \hash_hmac('sha256', $body, 'secret');

        $this->appendNewResponse(new Response(200, [RequestSigner::SHOPWARE_APP_SIGNATURE => $secret], $body));

        $this->browser->request('GET', '/store-api/checkout/gateway?orderId=' . $orderId);
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('payments', $response, 'Response has probably errors');
        static::assertIsArray($response['payments']);

        $paymentMethodIds = array_column($response['payments'], 'id');

        static::assertContains($this->ids->get('order-payment'), $paymentMethodIds);
        static::assertNotContains($this->ids->get('session-payment'), $paymentMethodIds);
        static::assertArrayHasKey('errors', $response);
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
        $app = [
            'id' => Uuid::randomHex(),
            'name' => 'Test app',
            'path' => 'test-app',
            'version' => '0.0.1',
            'active' => true,
            'checkoutGatewayUrl' => 'https://test-app.com/checkout-gateway',
            'appSecret' => 'secret',
            'integration' => [
                'label' => 'Test app',
                'accessKey' => 'foo',
                'secretAccessKey' => 'bar',
            ],
            'aclRole' => [
                'name' => 'foo',
                'privileges' => [
                    'checkout-gateway:read',
                    // granted so AppCheckoutGateway is allowed to call the app
                    'checkout_gateway',
                ],
            ],
            'translations' => [
                'en-GB' => [
                    'label' => 'Test app',
                ],
            ],
        ];

        static::getContainer()
            ->get('app.repository')
            ->create([$app], Context::createDefaultContext());

        $payments = [
            [
                'id' => $this->ids->create('payment'),
                'name' => 'Payment 1',
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
            ],
            [
                'id' => $this->ids->create('new-payment'),
                'name' => 'Payment 2',
                'technicalName' => 'payment_new-test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
            ],
        ];

        static::getContainer()
            ->get('payment_method.repository')
            ->create($payments, Context::createDefaultContext());
    }
}
