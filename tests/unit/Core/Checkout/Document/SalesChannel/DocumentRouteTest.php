<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
    public function testDownloadWithDocumentNotFound(): void
    {
        $generator = $this->createMock(DocumentGenerator::class);

        $route = new DocumentRoute(
            $generator,
            $this->createMock(EntityRepository::class)
        );

        static::expectException(DocumentException::class);
        static::expectExceptionMessage('The document with id "documentId" is invalid or could not be found.');

        $route->download('documentId', new Request(), $this->createMock(SalesChannelContext::class));
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

        $route = new DocumentRoute(
            $generator,
            $documentRepository
        );

        static::expectException(DocumentException::class);
        static::expectExceptionMessage('The order with id "test" is invalid or could not be found.');

        $route->download('documentId', new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testDownloadWithoutOrderCustomer(): void
    {
        $generator = $this->createMock(DocumentGenerator::class);

        $order = new OrderEntity();

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository
        );

        static::expectException(CustomerNotLoggedInException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $route->download('documentId', new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testThrowExceptionForNotGuestOrderForGuest(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(true);

        $generator = $this->createMock(DocumentGenerator::class);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        static::expectException(CustomerNotLoggedInException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $route->download('documentId', $request, $context);
    }

    public function testThrowExceptionWrongCredentialsForGuestAuthentication(): void
    {
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('zipcode');

        $customerId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setGuest(true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setCustomerId($customerId);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'not matching',
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        static::expectException(WrongGuestCredentialsException::class);
        static::expectExceptionMessage('Wrong credentials for guest authentication');

        $route->download($document->getId(), $request, $context);
    }

    public function testThrowExceptionGuestNotAuthenticated(): void
    {
        $customerId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setGuest(true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setCustomerId($customerId);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository
        );

        $request = new Request();

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        static::expectException(GuestNotAuthenticatedException::class);
        static::expectExceptionMessage('Guest not authenticated.');

        $route->download($document->getId(), $request, $context);
    }

    public function testThrowExceptionForGuestWithoutDeepLinkCode(): void
    {
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('zipcode');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'zipcode',
        ]);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        static::expectException(CustomerNotLoggedInException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $route->download('documentId', $request, $context);
    }

    public function testGuestCanDownload(): void
    {
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('zipcode');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(true);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('email');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $documentRepository,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'zipcode',
        ]);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $response = $route->download('documentId', $request, $context, 'deepLinkCode');

        static::assertSame($response->getStatusCode(), Response::HTTP_NO_CONTENT);
    }

    public function testThrowExceptionForNotMatchingCustomer(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $generator = $this->createMock(DocumentGenerator::class);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomerId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        static::expectException(CustomerNotLoggedInException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $route->download('documentId', $request, $context);
    }

    public function testMatchingCustomerCanDownload(): void
    {
        $customerID = Uuid::randomHex();

        $customer = new CustomerEntity();
        $customer->setId($customerID);
        $customer->setGuest(false);

        $generator = $this->createMock(DocumentGenerator::class);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setCustomerId($customerID);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($orderCustomer);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $generator,
            $documentRepository
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $response = $route->download('documentId', $request, $context);

        static::assertSame($response->getStatusCode(), Response::HTTP_NO_CONTENT);
    }
}
