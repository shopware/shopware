<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group store-api
 */
class DocumentRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderActionTrait, CustomerTestTrait {
        OrderActionTrait::login insteadof CustomerTestTrait;
    }

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $this->getContainer()->get(DocumentConfigLoader::class)->reset();
    }

    public function testNotLoggedinWithoutDeepLinkCode(): void
    {
        $email = 'guest@example.com';
        $password = 'guest@example.com';
        $customerId = $this->createCustomer($password, $email, true);
        $this->createOrder($customerId);
        $operation = new DocumentGenerateOperation($this->ids->get('order'));
        $document = $this->documentGenerator->generate('invoice', [$operation->getOrderId() => $operation], $this->ids->context)->first();

        $this->browser
            ->request(
                'GET',
                '/store-api/document/download/' . $document->getId(),
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testGuestWithCorrectDeepLinkCode(): void
    {
        $email = 'guest@example.com';
        $password = 'guest@example.com';
        $customerId = $this->createCustomer($password, $email, true);
        $this->createOrder($customerId);
        $operation = new DocumentGenerateOperation($this->ids->get('order'));
        $document = $this->documentGenerator->generate('invoice', [$operation->getOrderId() => $operation], $this->ids->context)->first();

        $token = $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $token);

        $this->browser
            ->request(
                'GET',
                '/store-api/document/download/' . $document->getId() . '/' . $document->getDeepLinkCode(),
                [
                ]
            );

        static::assertNotNull($this->browser->getResponse());

        $headers = $this->browser->getResponse()->headers;

        static::assertEquals('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
        static::assertEquals('application/pdf', $headers->get('content-type'));
    }

    public function testGuestWithIncorrectDeepLinkCode(): void
    {
        $email = 'guest@example.com';
        $password = 'guest@example.com';
        $customerId = $this->createCustomer($password, $email, true);
        $this->createOrder($customerId);
        $operation = new DocumentGenerateOperation($this->ids->get('order'));
        $document = $this->documentGenerator->generate('invoice', [$operation->getOrderId() => $operation], $this->ids->context)->first();

        $token = $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $token);

        $deeplinkCode = 'incorrect';
        $this->browser
            ->request(
                'GET',
                '/store-api/document/download/' . $document->getId() . '/' . $deeplinkCode,
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('DOCUMENT__INVALID_DOCUMENT_ID', $response['errors'][0]['code']);
    }

    public function testLoadWithCustomerLoggedInWithoutDeepLinkCode(): void
    {
        $email = 'guest@example.com';
        $password = 'guest@example.com';
        $customerId = $this->createCustomer($password, $email);
        $this->createOrder($customerId);
        $operation = new DocumentGenerateOperation($this->ids->get('order'));
        $document = $this->documentGenerator->generate('invoice', [$operation->getOrderId() => $operation], $this->ids->context)->first();

        $token = $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $token);

        $this->browser
            ->request(
                'GET',
                '/store-api/document/download/' . $document->getId(),
                [
                ]
            );

        static::assertNotNull($this->browser->getResponse());

        $headers = $this->browser->getResponse()->headers;

        static::assertEquals('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
        static::assertEquals('application/pdf', $headers->get('content-type'));
    }

    private function createOrder(string $customerId): void
    {
        $this->getContainer()->get('order.repository')->create([
            [
                'id' => $this->ids->create('order'),
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
                'orderNumber' => Uuid::randomHex(),
                'stateId' => $this->getStateMachineState(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'billingAddressId' => $billingAddressId = Uuid::randomHex(),
                'addresses' => [
                    [
                        'id' => $billingAddressId,
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                        'countryId' => $this->getValidCountryId(),
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $this->ids->create('line-item'),
                        'identifier' => $this->ids->create('line-item'),
                        'quantity' => 1,
                        'label' => 'label',
                        'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [
                    [
                        'id' => $this->ids->create('delivery'),
                        'shippingOrderAddressId' => $this->ids->create('shipping-address'),
                        'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                        'stateId' => $this->getStateId('open', 'order_delivery.state'),
                        'trackingCodes' => [],
                        'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'positions' => [
                            [
                                'id' => $this->ids->create('position'),
                                'orderLineItemId' => $this->ids->create('line-item'),
                                'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            ],
                        ],
                    ],
                ],
                'context' => '{}',
                'payload' => '{}',
            ],
        ], $this->ids->context);
    }
}
