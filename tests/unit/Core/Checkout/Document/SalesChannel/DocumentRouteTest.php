<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentRoute::class)]
class DocumentRouteTest extends TestCase
{
    private const DUMMY_DOCUMENT_ID = 'documentId';

    private const ACCEPT_WILDCARD = '*/*';

    private const CUSTOM_MIME_TYPE = 'application/custom-type';

    private const INVALID_FILE_TYPE = 'invalid';

    private const ACCEPT_HEADER_VALUE_BROWSER = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

    private const SUPPORTED_FILE_FORMATS = [
        PdfRenderer::FILE_EXTENSION => PdfRenderer::FILE_CONTENT_TYPE,
        HtmlRenderer::FILE_EXTENSION => HtmlRenderer::FILE_CONTENT_TYPE,
        ZugferdRenderer::FILE_EXTENSION => ZugferdRenderer::FILE_CONTENT_TYPE,
    ];

    public function testDownloadWithDocumentNotFound(): void
    {
        $generator = $this->createMock(DocumentGenerator::class);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $generator,
            $this->createMock(EntityRepository::class),
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $this->expectExceptionObject(DocumentException::documentNotFound('documentId'));

        $route->download(self::DUMMY_DOCUMENT_ID, new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testDownloadWithOrderNotFound(): void
    {
        $generator = $this->createMock(DocumentGenerator::class);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId('test');

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $this->expectExceptionObject(DocumentException::orderNotFound('test'));

        $route->download(self::DUMMY_DOCUMENT_ID, new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testDownloadWithoutOrderCustomer(): void
    {
        $generator = $this->createMock(DocumentGenerator::class);

        $order = new OrderEntity();
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $this->expectExceptionObject(DocumentException::customerNotLoggedIn());

        $route->download(self::DUMMY_DOCUMENT_ID, new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testThrowExceptionForNotGuestOrderForGuest(): void
    {
        $this->createCustomer(Uuid::randomHex(), true);

        $generator = $this->createMock(DocumentGenerator::class);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $this->expectExceptionObject(DocumentException::customerNotLoggedIn());

        $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);
    }

    public function testThrowExceptionWrongCredentialsForGuestAuthentication(): void
    {
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('zipcode');

        $customerId = Uuid::randomHex();
        $customer = $this->createCustomer($customerId, true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setCustomerId($customerId);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'not matching',
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $this->expectExceptionObject(CustomerException::wrongGuestCredentials());

        $route->download($document->getId(), $request, $context);
    }

    public function testThrowExceptionGuestNotAuthenticated(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->createCustomer($customerId, true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setCustomerId($customerId);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request();

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $this->expectExceptionObject(CustomerException::guestNotAuthenticated());

        $route->download($document->getId(), $request, $context);
    }

    public function testThrowExceptionForGuestWithoutDeepLinkCode(): void
    {
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('zipcode');

        $customer = $this->createCustomer(Uuid::randomHex(), true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'zipcode',
        ]);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        static::expectException(CustomerNotLoggedInException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);
    }

    public function testGuestCanDownload(): void
    {
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('zipcode');

        $customer = $this->createCustomer(Uuid::randomHex(), true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setCustomerId($customer->getId());
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'zipcode',
        ]);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(
                DocumentException::documentFileTypeUnavailable(
                    self::DUMMY_DOCUMENT_ID,
                    [PdfRenderer::FILE_EXTENSION],
                )
            );
        }

        $response = $route->download(self::DUMMY_DOCUMENT_ID, $request, $context, 'deepLinkCode');

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }
    }

    public function testThrowExceptionForNotMatchingCustomer(): void
    {
        $customer = $this->createCustomer(Uuid::randomHex(), false);
        $order = $this->createOrder(Uuid::randomHex());
        $document = $this->createDocument($order);

        $generator = $this->createMock(DocumentGenerator::class);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        static::expectException(CustomerException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);
    }

    public function testMatchingCustomerCanDownload(): void
    {
        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        $generator = $this->createMock(DocumentGenerator::class);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $this->createMock(AbstractDocumentTypeRenderer::class),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(
                DocumentException::documentFileTypeUnavailable(
                    self::DUMMY_DOCUMENT_ID,
                    [PdfRenderer::FILE_EXTENSION],
                )
            );
        }

        $response = $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }
    }

    public function testDownloadShouldThrowExceptionWhenDocumentIsNotFound(): void
    {
        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $generatorMock = $this->createMock(DocumentGenerator::class);
        $pdfFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);
        $htmlFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $pdfFileRendererMock,
            HtmlRenderer::FILE_EXTENSION => $htmlFileRendererMock,
        ]);

        $pdfFileRendererMock->method('getContentType')->willReturn(PdfRenderer::FILE_CONTENT_TYPE);
        $htmlFileRendererMock->method('getContentType')->willReturn(HtmlRenderer::FILE_CONTENT_TYPE);

        $route = new DocumentRoute(
            $generatorMock,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock,
        );

        $request = new Request();
        $request->headers->set('Accept', PdfRenderer::FILE_CONTENT_TYPE);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(
                DocumentException::documentFileTypeUnavailable(
                    self::DUMMY_DOCUMENT_ID,
                    [PdfRenderer::FILE_EXTENSION]
                )
            );
        }

        $response = $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }
    }

    #[DataProvider('provideAcceptHeaderThatFallbacksToDefaultFileType')]
    public function testDownloadShouldFallbackToDefaultFileType(
        ?string $acceptHeader
    ): void {
        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $generator = $this->createMock(DocumentGenerator::class);
        $pdfFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);

        $fileRenderers = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $pdfFileRendererMock,
        ]);

        $pdfFileRendererMock->method('getContentType')->willReturn(PdfRenderer::FILE_CONTENT_TYPE);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderers,
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $request = new Request();
        if ($acceptHeader) {
            $request->headers->set('Accept', $acceptHeader);
        }

        $generator->expects($this->once())
            ->method('readDocument')
            ->with(
                self::DUMMY_DOCUMENT_ID,
                $context->getContext(),
                '',
                PdfRenderer::FILE_EXTENSION,
            )
            ->willReturn(new RenderedDocument());

        $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);
    }

    public static function provideAcceptHeaderThatFallbacksToDefaultFileType(): \Generator
    {
        yield 'Accept header with wildcart: ' . self::ACCEPT_WILDCARD => [
            'acceptHeader' => self::ACCEPT_WILDCARD,
        ];
        yield 'Accept header not set should use default fileType' => [
            'acceptHeader' => null,
        ];
    }

    #[DataProvider('provideAcceptHeaderValues')]
    public function testDownloadWithAcceptHeaderMimeTypes(
        string $acceptHeader,
        string $expectedFileExtension
    ): void {
        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $generator = $this->createMock(DocumentGenerator::class);
        $pdfFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);
        $htmlFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $pdfFileRendererMock,
            HtmlRenderer::FILE_EXTENSION => $htmlFileRendererMock,
        ]);

        $pdfFileRendererMock->method('getContentType')->willReturn(PdfRenderer::FILE_CONTENT_TYPE);
        $htmlFileRendererMock->method('getContentType')->willReturn(HtmlRenderer::FILE_CONTENT_TYPE);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->headers->set('Accept', $acceptHeader);

        $generator->expects($this->once())
            ->method('readDocument')
            ->with(
                self::DUMMY_DOCUMENT_ID,
                $context->getContext(),
                '',
                $expectedFileExtension,
            )
            ->willReturn(new RenderedDocument());

        $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);
    }

    public static function provideAcceptHeaderValues(): \Generator
    {
        yield 'accept header ' . ZugferdRenderer::FILE_CONTENT_TYPE . '" requests xml document' => [
            'acceptHeader' => ZugferdRenderer::FILE_CONTENT_TYPE,
            'expectedFileExtension' => ZugferdRenderer::FILE_EXTENSION,
        ];

        yield 'accept header "' . HtmlRenderer::FILE_CONTENT_TYPE . '" requests html document' => [
            'acceptHeader' => HtmlRenderer::FILE_CONTENT_TYPE,
            'expectedFileExtension' => HtmlRenderer::FILE_EXTENSION,
        ];

        yield 'accept header "application/pdf;q=0.4,text/html;q=0.8,application/xml;q=0.2" requests html document' => [
            'acceptHeader' => 'application/pdf;q=0.4,text/html;q=0.8,application/xml;q=0.2',
            'expectedFileExtension' => HtmlRenderer::FILE_EXTENSION,
        ];
    }

    public function testDownloadWithUnsupportedMimeTypeShouldNotCallReadDocumentAndThrowException(): void
    {
        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $generator = $this->createMock(DocumentGenerator::class);
        $pdfFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);
        $htmlFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $pdfFileRendererMock,
            HtmlRenderer::FILE_EXTENSION => $htmlFileRendererMock,
        ]);

        $pdfFileRendererMock->method('getContentType')->willReturn(PdfRenderer::FILE_CONTENT_TYPE);
        $htmlFileRendererMock->method('getContentType')->willReturn(HtmlRenderer::FILE_CONTENT_TYPE);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->headers->set('Accept', self::CUSTOM_MIME_TYPE);

        $generator->expects($this->never())->method('readDocument');

        $this->expectExceptionObject(
            DocumentException::documentAcceptHeaderMimeTypesNotSupported(
                [self::CUSTOM_MIME_TYPE],
                array_values(self::SUPPORTED_FILE_FORMATS),
            )
        );

        $route->download(self::DUMMY_DOCUMENT_ID, $request, $context);
    }

    public function testDownloadWithInvalidFileTypeParameterShouldNotCallReadDocumentAndThrowException(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $generator = $this->createMock(DocumentGenerator::class);
        $pdfFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);
        $htmlFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $pdfFileRendererMock,
            HtmlRenderer::FILE_EXTENSION => $htmlFileRendererMock,
        ]);

        $pdfFileRendererMock->method('getContentType')->willReturn(PdfRenderer::FILE_CONTENT_TYPE);
        $htmlFileRendererMock->method('getContentType')->willReturn(HtmlRenderer::FILE_CONTENT_TYPE);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->headers->set('Accept', self::ACCEPT_HEADER_VALUE_BROWSER);

        $generator->expects($this->never())->method('readDocument');

        $this->expectExceptionObject(
            DocumentException::documentFileTypeNotSupported(self::INVALID_FILE_TYPE)
        );

        $route->download(
            self::DUMMY_DOCUMENT_ID,
            $request,
            $context,
            '',
            self::INVALID_FILE_TYPE
        );
    }

    public function testDownloadWithInvalidFileTypeParameterCallsReadDocumentAndReturnsJustCodeInResponse(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $customerID = Uuid::randomHex();
        $customer = $this->createCustomer($customerID, false);
        $order = $this->createOrder($customerID);
        $document = $this->createDocument($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $generator = $this->createMock(DocumentGenerator::class);
        $pdfFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);
        $htmlFileRendererMock = $this->createMock(AbstractDocumentTypeRenderer::class);

        $fileRenderersMock = new \ArrayIterator([
            PdfRenderer::FILE_EXTENSION => $pdfFileRendererMock,
            HtmlRenderer::FILE_EXTENSION => $htmlFileRendererMock,
        ]);

        $pdfFileRendererMock->method('getContentType')->willReturn(PdfRenderer::FILE_CONTENT_TYPE);
        $htmlFileRendererMock->method('getContentType')->willReturn(HtmlRenderer::FILE_CONTENT_TYPE);

        $route = new DocumentRoute(
            $generator,
            $documentRepository,
            new GuestAuthenticator(),
            $fileRenderersMock
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->headers->set('Accept', self::ACCEPT_HEADER_VALUE_BROWSER);

        $generator->expects($this->once())
            ->method('readDocument')
            ->with(
                self::DUMMY_DOCUMENT_ID,
                $context->getContext(),
                '',
                self::INVALID_FILE_TYPE,
            );

        $response = $route->download(
            self::DUMMY_DOCUMENT_ID,
            $request,
            $context,
            '',
            self::INVALID_FILE_TYPE
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function createCustomer(string $customerId, bool $isGuest): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setGuest($isGuest);

        return $customer;
    }

    private function createOrder(string $customerId): OrderEntity
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomerId($customerId);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        return $order;
    }

    private function createDocument(OrderEntity $order): DocumentEntity
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrder($order);

        return $document;
    }
}
