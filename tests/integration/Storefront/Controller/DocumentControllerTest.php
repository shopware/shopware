<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
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
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class DocumentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private const CUSTOMER_EMAIL_ADDRESS = 'customer@example.com';

    private const INVALID_FILE_TYPE = 'invalid';

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DocumentGenerator $documentGenerator;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    private DocumentConfigLoader $documentConfigLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->documentConfigLoader = static::getContainer()->get(DocumentConfigLoader::class);
        // Clear cached config from previous tests to ensure a fresh state
        $this->documentConfigLoader->reset();

        $this->context = Context::createDefaultContext();

        $paymentMethod = $this->getAvailablePaymentMethod();

        $customerId = $this->createCustomer($paymentMethod->getId());
        $shippingMethod = $this->getAvailableShippingMethod();
        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethod->getId(),
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            ]
        );

        $ruleIds = [];
        if ($shippingRuleId = $shippingMethod->getAvailabilityRuleId()) {
            $ruleIds[] = $shippingRuleId;
        }
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

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            $context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $expectedFileContent = 'simple invoice';
        $expectedContentType = 'application/pdf';

        $request = new Request([], [], [], [], [], [], $expectedFileContent);
        $request->query->set('fileName', $fileName);
        $request->server->set('HTTP_CONTENT_TYPE', $expectedContentType);
        $request->server->set('HTTP_CONTENT_LENGTH', (string) mb_strlen($expectedFileContent));
        $request->headers->set('content-length', (string) mb_strlen($expectedFileContent));

        $request->query->set('extension', 'pdf');

        $documentIdStruct = $this->documentGenerator->upload(
            $document->getId(),
            $context,
            $request
        );

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);

        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/document/' . $documentIdStruct->getId() . '/' . $documentIdStruct->getDeepLinkCode(),
            $this->tokenize('frontend.account.order.single.document', ['fileType' => 'pdf'])
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame($expectedFileContent, $response->getContent());
        static::assertSame($expectedContentType, $response->headers->get('content-type'));

        // Customer are unable to view the document without valid deepLinkCode
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/document/' . $documentIdStruct->getId() . '/' . Random::getAlphanumericString(32),
            $this->tokenize('frontend.account.order.single.document', [])
        );

        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, string> $operationConfig
     */
    #[DataProvider('provideFileTypeParams')]
    public function testDownloadDocument(
        string $documentType,
        string $expectedFileType,
        string $expectedContentType,
        ?string $pathParameter,
        ?string $queryParameter,
        ?string $acceptHeader = null,
        array $operationConfig = [],
    ): void {
        $context = Context::createDefaultContext();

        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $operationConfig);

        $result = $this->documentGenerator->generate(
            $documentType,
            [$operation->getOrderId() => $operation],
            $context,
        );

        $document = $result->getSuccess()->first();

        static::assertNotNull($document, implode(', ', array_map(
            static fn (\Throwable $e) => $e->getMessage(),
            $result->getErrors(),
        )));

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);

        $browser->request(
            'GET',
            '/account/order/document/' . $document->getId() . '/' . $document->getDeepLinkCode()
            . ($pathParameter ? '/' . $pathParameter : '')
            . ($queryParameter ? '?fileType=' . $queryParameter : ''),
            [],
            [],
            $acceptHeader ? ['HTTP_ACCEPT' => 'application/pdf'] : [],
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($response->getContent());

        $documentEntity = $this->documentRepository->search(new Criteria([$document->getId()]), $context)->getEntities()->first();
        static::assertNotNull($documentEntity);

        $documentConfig = $this->documentConfigLoader->load(InvoiceRenderer::TYPE, TestDefaults::SALES_CHANNEL, $context);
        $expectedFilename = $documentConfig->getFilenamePrefix() . $documentEntity->getDocumentNumber() . $documentConfig->getFilenameSuffix();

        static::assertSame(
            'inline; filename=' . $expectedFilename . '.' . $expectedFileType,
            $response->headers->get('content-disposition')
        );
        static::assertStringContainsString(
            $expectedContentType,
            (string) $response->headers->get('content-type')
        );
    }

    public static function provideFileTypeParams(): \Generator
    {
        yield 'with path param pdf' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => PdfRenderer::FILE_EXTENSION,
            'queryParameter' => null,
        ];

        yield 'with query param html' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => HtmlRenderer::FILE_EXTENSION,
            'expectedContentType' => HtmlRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => null,
            'queryParameter' => HtmlRenderer::FILE_EXTENSION,
        ];

        yield 'with path param xml' => [
            'documentType' => ZugferdRenderer::TYPE,
            'expectedFileType' => ZugferdRenderer::FILE_EXTENSION,
            'expectedContentType' => ZugferdRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => ZugferdRenderer::FILE_EXTENSION,
            'queryParameter' => null,
            'acceptHeader' => null,
            'operationConfig' => [
                'vatId' => 'DE123456789',
                'bankBic' => 'DEUTDEDBFRA',
                'bankIban' => 'DE89370400440532013000',
                'bankName' => 'Deutsche Bank',
                'taxOffice' => 'Finanzamt Musterstadt',
                'companyUrl' => 'https://www.shopware.com',
                'companyName' => 'Example Company',
                'companyEmail' => 'mail@shopware.com',
                'companyPhone' => '+49 123 4567890',
                'paymentDueDate' => '+30 days',
                'executiveDirector' => 'Max Mustermann',
                'placeOfFulfillment' => 'Musterstadt',
                'placeOfJurisdiction' => 'Musterstadt',
            ],
        ];

        yield 'without params pdf should be returned' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => null,
            'queryParameter' => null,
        ];

        yield 'Accept header should be ignored and HTML should be returned' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => HtmlRenderer::FILE_EXTENSION,
            'expectedContentType' => HtmlRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => HtmlRenderer::FILE_EXTENSION,
            'queryParameter' => null,
            'acceptHeader' => 'application/' . PdfRenderer::FILE_EXTENSION,
        ];
    }

    public function testDownloadDocumentShouldThrowExceptionWithInvalidFileTypeParameter(): void
    {
        $context = Context::createDefaultContext();

        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId);

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            $context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);

        $browser->request(
            'GET',
            '/account/order/document/'
            . $document->getId() . '/'
            . $document->getDeepLinkCode() . '/'
            . self::INVALID_FILE_TYPE,
        );

        $response = $browser->getResponse();

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        } else {
            static::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
            static::assertStringContainsString(\sprintf('The requested file type is not supported: %s. (406 Not Acceptable)', self::INVALID_FILE_TYPE), (string) $response->getContent());
        }
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'shopware',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

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

        static::getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $cart = static::getContainer()->get(Processor::class)->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $cart = static::getContainer()->get(CartService::class)->recalculate($cart, $this->salesChannelContext);

        return static::getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);
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
            'email' => self::CUSTOMER_EMAIL_ADDRESS,
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

        static::getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
    }
}
