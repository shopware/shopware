<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\SalesChannel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[Group('store-api')]
class DocumentRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /*
     * import of two traits that both define login() with different parameters.
     * The conflict is resolved by using insteadof and internal calls inside OrderActionTrait can still use OrderActionTrait::login()
     * With the alias loginBrowser() the SalesChannelApiTestBehaviour::login() can be called.
     */
    use OrderActionTrait, SalesChannelApiTestBehaviour {
        OrderActionTrait::login insteadof SalesChannelApiTestBehaviour;
        SalesChannelApiTestBehaviour::login as loginBrowser;
    }
    private const INVALID_FILE_TYPE = 'invalid';

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        static::getContainer()->get(DocumentConfigLoader::class)->reset();

        $this->createCustomer(null, false, ['id' => $this->ids->get('customer')]);
        $this->createCustomer(null, true, ['id' => $this->ids->get('guest')]);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    /**
     * @param array<string, mixed> $requestParameters
     * @param class-string<HttpException>|null $expectedException
     */
    #[DataProvider('documentDownloadRouteDataProvider')]
    public function testDownload(
        string $orderCustomerId,
        ?string $loggedInCustomerId,
        array $requestParameters,
        ?bool $withValidDeepLinkCode,
        ?string $expectedException = null,
        ?string $expectedErrorCode = null,
    ): void {
        if (!$this->ids->has($orderCustomerId)) {
            $this->createCustomer(null, false, ['id' => $this->ids->get($orderCustomerId)]);
        }

        if ($loggedInCustomerId !== null && !$this->ids->has($loggedInCustomerId)) {
            $this->createCustomer(null, false, ['id' => $this->ids->get($loggedInCustomerId)]);
        }

        $this->createOrder($this->ids->get($orderCustomerId));

        $salesChannelContext = $this->createSalesChannelContext([], [
            'customerId' => $loggedInCustomerId !== null ? $this->ids->get($loggedInCustomerId) : null,
        ]);

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $deepLinkCode = '';
        if ($withValidDeepLinkCode !== null) {
            $deepLinkCode = $withValidDeepLinkCode ? $document->getDeepLinkCode() : Uuid::randomHex();
        }

        $request = new Request([], $requestParameters);

        $documentRoute = static::getContainer()->get(DocumentRoute::class);

        try {
            $response = $documentRoute->download(
                $document->getId(),
                $request,
                $salesChannelContext,
                $deepLinkCode
            );
        } catch (HttpException $e) {
            if (!$expectedException) {
                throw $e;
            }

            static::assertInstanceOf($expectedException, $e);
            static::assertSame($expectedErrorCode, $e->getErrorCode());

            return;
        }

        $headers = $response->headers;

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotEmpty($response->getContent());
        static::assertSame('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
        static::assertSame('application/pdf', $headers->get('content-type'));
    }

    public static function documentDownloadRouteDataProvider(): \Generator
    {
        // valid email and zipcode are 'test@example.com' and '48624', see OrderActionTrait

        yield 'logged in guest with valid deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
        ];

        yield 'logged in guest without deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => null,
            'expectedException' => CustomerNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'logged in guest with invalid deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'guest with correct request params and valid deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => true,
        ];

        yield 'guest without request params' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => null,
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
            'expectedException' => GuestNotAuthenticatedException::class,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_NOT_AUTHENTICATED,
        ];

        yield 'guest with invalid request params' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => null,
            'requestParameters' => [
                'email' => 'invalid',
                'zipcode' => 'invalid',
            ],
            'withValidDeepLinkCode' => true,
            'expectedException' => WrongGuestCredentialsException::class,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_WRONG_CREDENTIALS,
        ];

        yield 'guest with correct request params and without deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => null,
            'expectedException' => CustomerNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'guest with correct request params and invalid deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'customer with valid deep link code' => [
            'orderCustomerId' => 'customer',
            'loggedInCustomerId' => 'customer',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
        ];

        yield 'customer with invalid deep link code' => [
            'orderCustomerId' => 'customer',
            'loggedInCustomerId' => 'customer',
            'requestParameters' => [],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'customer without deep link code' => [
            'orderCustomerId' => 'customer',
            'loggedInCustomerId' => 'customer',
            'requestParameters' => [],
            'withValidDeepLinkCode' => null,
            'expectedException' => CustomerNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'different customer with valid deep link code' => [
            'orderCustomerId' => 'customer',
            'loggedInCustomerId' => 'different-customer',
            'requestParameters' => [],
            'withValidDeepLinkCode' => null,
            'expectedException' => CustomerNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'order by guest but logged in customer with valid deep link code' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => 'customer',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
            'expectedException' => GuestNotAuthenticatedException::class,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_NOT_AUTHENTICATED,
        ];

        yield 'order by guest but logged in customer with valid deep link code with correct request params' => [
            'orderCustomerId' => 'guest',
            'loggedInCustomerId' => 'customer',
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => true,
        ];

        yield 'order by customer but guest with with correct request params' => [
            'orderCustomerId' => 'customer',
            'loggedInCustomerId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => true,
            'expectedException' => CustomerNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'order by customer but logged in guest' => [
            'orderCustomerId' => 'customer',
            'loggedInCustomerId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
            'expectedException' => CustomerNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];
    }

    #[DataProvider('provideRequestParameters')]
    public function testDownloadWithDifferentParameterTypesForFileType(
        string $documentType,
        string $expectedFileType,
        string $expectedContentType,
        ?string $queryParameter,
        ?string $pathParameter,
    ): void {
        $customerId = $this->loginBrowser($this->browser);
        $this->createOrder(
            $customerId,
            ['salesChannelId' => $this->ids->get('sales-channel')]
        );

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            $documentType,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $documentId = $document->getId();

        $this->browser->request(
            'GET',
            '/store-api/document/download/' . $documentId . '/' . $document->getDeepLinkCode()
            . ($pathParameter ? '/' . $pathParameter : '')
            . ($queryParameter ? '?fileType=' . $queryParameter : ''),
        );

        $response = $this->browser->getResponse();

        static::assertNotEmpty($response->getContent());
        static::assertSame('inline; filename=invoice_1000.' . $expectedFileType, $response->headers->get('content-disposition'));
        static::assertStringContainsString($expectedContentType, (string) $response->headers->get('content-type'));
    }

    public static function provideRequestParameters(): \Generator
    {
        yield 'query param fileType = html' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => HtmlRenderer::FILE_EXTENSION,
            'expectedContentType' => HtmlRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => HtmlRenderer::FILE_EXTENSION,
            'pathParameter' => null,
        ];

        yield 'path param html as fileType' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => HtmlRenderer::FILE_EXTENSION,
            'expectedContentType' => HtmlRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => null,
            'pathParameter' => HtmlRenderer::FILE_EXTENSION,
        ];

        yield 'query param fileType = pdf' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => PdfRenderer::FILE_EXTENSION,
            'pathParameter' => null,
        ];

        yield 'path param pdf as fileType' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => null,
            'pathParameter' => PdfRenderer::FILE_EXTENSION,
        ];

        yield 'query param fileType = xml' => [
            'documentType' => ZugferdRenderer::TYPE,
            'expectedFileType' => ZugferdRenderer::FILE_EXTENSION,
            'expectedContentType' => ZugferdRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => ZugferdRenderer::FILE_EXTENSION,
            'pathParameter' => null,
        ];

        yield 'path param xml as fileType' => [
            'documentType' => ZugferdRenderer::TYPE,
            'expectedFileType' => ZugferdRenderer::FILE_EXTENSION,
            'expectedContentType' => ZugferdRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => null,
            'pathParameter' => ZugferdRenderer::FILE_EXTENSION,
        ];

        yield 'without params the fallback should be used' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'queryParameter' => null,
            'pathParameter' => null,
        ];
    }

    #[DataProvider('provideInvalidRequestParameters')]
    public function testDownloadWithInvalidFileTypeShouldThrowException(
        ?string $queryParameter,
        ?string $pathParameter,
    ): void {
        $customerId = $this->loginBrowser($this->browser);
        $this->createOrder(
            $customerId,
            ['salesChannelId' => $this->ids->get('sales-channel')]
        );

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $documentId = $document->getId();

        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(
                DocumentException::documentFileTypeUnavailable($documentId, self::INVALID_FILE_TYPE)
            );
        }

        $this->browser->request(
            'GET',
            '/store-api/document/download/' . $documentId . '/' . $document->getDeepLinkCode()
            . ($pathParameter ? '/' . $pathParameter : '')
            . ($queryParameter ? '?fileType=' . $queryParameter : ''),
        );

        $response = $this->browser->getResponse();

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }
    }

    public static function provideInvalidRequestParameters(): \Generator
    {
        yield 'invalid query param' => [
            'queryParameter' => null,
            'pathParameter' => self::INVALID_FILE_TYPE,
        ];

        yield 'invalid path param' => [
            'queryParameter' => self::INVALID_FILE_TYPE,
            'pathParameter' => null,
        ];
    }
}
