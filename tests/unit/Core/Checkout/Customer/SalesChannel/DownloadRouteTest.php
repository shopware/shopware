<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(DownloadRoute::class)]
class DownloadRouteTest extends TestCase
{
    private MockObject&EntityRepository $downloadRepository;

    private MockObject&DownloadResponseGenerator $downloadResponseGenerator;

    private MockObject&SalesChannelContext $salesChannelContext;

    private DownloadRoute $downloadRoute;

    protected function setUp(): void
    {
        $this->downloadRepository = $this->createMock(EntityRepository::class);
        $this->downloadResponseGenerator = $this->createMock(DownloadResponseGenerator::class);
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $this->downloadRoute = new DownloadRoute(
            $this->downloadRepository,
            $this->downloadResponseGenerator
        );
    }

    public function testGetDecoratedThrowsException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->downloadRoute->getDecorated();
    }

    public function testCustomerNotLoggedInException(): void
    {
        static::expectException(CustomerException::class);
        static::expectExceptionMessage('Customer is not logged in.');

        $this->downloadRoute->load(new Request(), $this->salesChannelContext);
    }

    public function testMissingRequestParameterException(): void
    {
        $this->salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        static::expectException(RoutingException::class);
        $this->downloadRoute->load(new Request(), $this->salesChannelContext);
    }

    public function testDownloadNotExistingThrowsException(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foobar');
        $this->salesChannelContext->method('getCustomer')->willReturn($customer);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $this->downloadRepository->method('search')->willReturn($searchResult);

        $request = new Request();
        $request->request->set('downloadId', 'foo');
        $request->request->set('orderId', 'bar');

        static::expectException(CustomerException::class);
        static::expectExceptionMessage('Line item download file with id "foo" not found.');
        $this->downloadRoute->load($request, $this->salesChannelContext);
    }

    public function testReturnsResponse(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foobar');
        $this->salesChannelContext->method('getCustomer')->willReturn($customer);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $download = new OrderLineItemDownloadEntity();
        $download->setMedia(new MediaEntity());
        $searchResult->method('first')->willReturn($download);
        $this->downloadRepository->method('search')->willReturn($searchResult);

        $this->downloadResponseGenerator->method('getResponse')->willReturn(new Response());

        $request = new Request();
        $request->request->set('downloadId', 'foo');
        $request->request->set('orderId', 'bar');

        $response = $this->downloadRoute->load($request, $this->salesChannelContext);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
