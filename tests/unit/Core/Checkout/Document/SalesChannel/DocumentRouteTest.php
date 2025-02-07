<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentRoute::class)]
class DocumentRouteTest extends TestCase
{
    public function testDownloadsDocumentSuccessfully(): void
    {
        $document = new RenderedDocument();
        $document->setContent('');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $generator = $this->createMock(DocumentGenerator::class);
        $generator->expects(static::once())
            ->method('readDocument')
            ->willReturn($document);

        $route = new DocumentRoute(
            $generator,
            $this->createMock(EntityRepository::class)
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $response = $route->download('documentId', $request, $context);

        static::assertSame($response->getStatusCode(), JsonResponse::HTTP_OK);
    }

    public function testDownloadWithDocumentNotFound(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $generator = $this->createMock(DocumentGenerator::class);

        $route = new DocumentRoute(
            $generator,
            $this->createMock(EntityRepository::class)
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $response = $route->download('documentId', $request, $context);

        static::assertSame($response->getStatusCode(), JsonResponse::HTTP_NO_CONTENT);
    }

    public function testThrowExceptionWithoutDeeplink(): void
    {
        static::expectException(DocumentException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(true);

        $generator = $this->createMock(DocumentGenerator::class);

        $route = new DocumentRoute(
            $generator,
            $this->createMock(EntityRepository::class)
        );

        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $route->download('documentId', $request, $context);
    }

    public function testThrowExceptionWrongCredentialsForGuestAuthentication(): void
    {
        static::expectException(DocumentException::class);
        static::expectExceptionMessage('Wrong credentials for guest authentication');

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setZipcode('12345');

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

        /** @var StaticEntityRepository<DocumentCollection> $repository */
        $repository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $repository,
        );

        $request = new Request([
            'email' => 'email',
            'zipcode' => 'zipcode',
        ]);

        $route->download($document->getId(), $request, $this->createMock(SalesChannelContext::class));
    }

    public function testThrowExceptionGuestNotAuthenticated(): void
    {
        static::expectException(DocumentException::class);
        static::expectExceptionMessage('Guest not authenticated.');

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

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $document->setOrder($order);

        /** @var StaticEntityRepository<DocumentCollection> $repository */
        $repository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ]);

        $route = new DocumentRoute(
            $this->createMock(DocumentGenerator::class),
            $repository
        );

        $request = new Request();

        $route->download($document->getId(), $request, $this->createMock(SalesChannelContext::class));
    }
}
