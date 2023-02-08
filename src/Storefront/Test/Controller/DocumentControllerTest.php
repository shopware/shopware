<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('customer-order')]
class DocumentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $paymentMethod = $this->getAvailablePaymentMethod();

        $customerId = $this->createCustomer($paymentMethod->getId());
        $shippingMethod = $this->getAvailableShippingMethod();
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethod->getId(),
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            ]
        );

        $ruleIds = [$shippingMethod->getAvailabilityRuleId()];
        if ($paymentRuleId = $paymentMethod->getAvailabilityRuleId()) {
            $ruleIds[] = $paymentRuleId;
        }
        $this->salesChannelContext->setRuleIds($ruleIds);
    }

    public function testCustomerAbleToViewUploadDocumentWithDeepLinkCode(): void
    {
        $context = Context::createDefaultContext();

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);
        $fileName = 'invoice';

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], null, true);

        $document = $this->getContainer()->get(DocumentGenerator::class)->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            $context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $expectedFileContent = 'simple invoice';
        $expectedContentType = 'text/plain; charset=UTF-8';

        $request = new Request([], [], [], [], [], [], $expectedFileContent);
        $request->query->set('fileName', $fileName);
        $request->server->set('HTTP_CONTENT_TYPE', $expectedContentType);
        $request->server->set('HTTP_CONTENT_LENGTH', (string) mb_strlen($expectedFileContent));
        $request->headers->set('content-length', (string) mb_strlen($expectedFileContent));

        $request->query->set('extension', 'txt');

        $documentIdStruct = $this->getContainer()->get(DocumentGenerator::class)->upload(
            $document->getId(),
            $context,
            $request
        );

        $browser = $this->login('customer@example.com', 'shopware');

        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/document/' . $documentIdStruct->getId() . '/' . $documentIdStruct->getDeepLinkCode(),
            $this->tokenize('frontend.account.order.single.document', [])
        );

        $response = $browser->getResponse();

        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals($expectedFileContent, $response->getContent());
        static::assertEquals($expectedContentType, $response->headers->get('content-type'));

        // Customer are unable to view the document without valid deepLinkCode
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/document/' . $documentIdStruct->getId() . '/' . Random::getAlphanumericString(32),
            $this->tokenize('frontend.account.order.single.document', [])
        );

        static::assertEquals(400, $browser->getResponse()->getStatusCode());
    }

    private function login(string $email, string $password): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => $password,
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        return $browser;
    }

    /**
     * @throws CartException
     * @throws \Exception
     */
    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cart = new Cart('a-b-c');

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $id = Uuid::randomHex();

            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' product');

            $products[] = [
                'id' => $id,
                'name' => $name,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false],
                ],
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => ['id' => $id, 'name' => 'test'],
                'tax' => ['id' => $id, 'taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];

            $cart->add($factory->create(['id' => $id, 'referencedId' => $id], $this->salesChannelContext));
            $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);
        }

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $cart = $this->getContainer()->get(CartService::class)->recalculate($cart, $this->salesChannelContext);

        return $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);
    }

    private function createCustomer(string $paymentMethodId): string
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
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => 'customer@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $paymentMethodId,
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

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
    }
}
