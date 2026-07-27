<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DownloadRoute::class)]
class DownloadRouteTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<OrderLineItemDownloadCollection>
     */
    private Stub&EntityRepository $downloadRepository;

    private Stub&DownloadResponseGenerator $downloadResponseGenerator;

    private Stub&SalesChannelContext $salesChannelContext;

    private DownloadRoute $downloadRoute;

    protected function setUp(): void
    {
        $this->downloadRepository = static::createStub(EntityRepository::class);
        $this->downloadResponseGenerator = static::createStub(DownloadResponseGenerator::class);
        $this->salesChannelContext = static::createStub(SalesChannelContext::class);

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
        $this->expectExceptionObject(CustomerException::customerNotLoggedIn());

        $this->downloadRoute->load(new Request(), $this->salesChannelContext);
    }

    public function testMissingRequestParameterException(): void
    {
        $this->salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(RoutingException::class);
        } else {
            $this->expectException(CustomerException::class);
        }
        $this->downloadRoute->load(new Request(), $this->salesChannelContext);
    }

    public function testDownloadNotExistingThrowsException(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foobar');
        $this->salesChannelContext->method('getCustomer')->willReturn($customer);

        $searchResult = static::createStub(EntitySearchResult::class);
        $this->downloadRepository->method('search')->willReturn($searchResult);

        $request = new Request();
        $request->attributes->set('downloadId', 'foo');
        $request->attributes->set('orderId', 'bar');

        $this->expectExceptionObject(CustomerException::downloadFileNotFound('foo'));
        $this->downloadRoute->load($request, $this->salesChannelContext);
    }

    public function testReturnsResponse(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('foobar');
        $this->salesChannelContext->method('getCustomer')->willReturn($customer);

        $searchResult = static::createStub(EntitySearchResult::class);
        $download = new OrderLineItemDownloadEntity();
        $download->setId('foo');
        $download->setMedia(new MediaEntity());
        $searchResult->method('getEntities')->willReturn(new OrderLineItemDownloadCollection([$download]));
        $this->downloadRepository->method('search')->willReturn($searchResult);

        $this->downloadResponseGenerator->method('getResponse')->willReturn(new Response());

        $request = new Request();
        $request->attributes->set('downloadId', 'foo');
        $request->attributes->set('orderId', 'bar');

        $response = $this->downloadRoute->load($request, $this->salesChannelContext);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
