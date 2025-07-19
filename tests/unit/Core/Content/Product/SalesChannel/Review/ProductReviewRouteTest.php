<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ProductReviewRoute::class)]
class ProductReviewRouteTest extends TestCase
{
    private MockObject&EntityRepository $repository;

    private StaticSystemConfigService $config;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private ProductReviewRoute $route;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->config = new StaticSystemConfigService([
            'test' => [
                'core.listing.showReview' => true,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewNotActive' => [
                'core.listing.showReview' => false,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
        ]);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->route = new ProductReviewRoute(
            $this->repository,
            $this->config,
            $this->eventDispatcher
        );
    }

    public function testLoad(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->once())->method('getCustomer')->willReturn($customer);
        $salesChannelContext->expects($this->exactly(1))->method('getSalesChannelId')->willReturn('test');
        $salesChannelContext->expects($this->exactly(1))->method('getContext')->willReturn($context);

        $expectedCriteria = new Criteria();
        $expectedCriteria->setTitle('product-review-route');
        $expectedCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('status', true),
                    new EqualsFilter('customerId', $customer->getId()),
                ]),
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with($expectedCriteria, $context);

        $event = new AddCacheTagEvent($this->route::buildName($productId));
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->route->load(
            $productId,
            new Request(),
            $salesChannelContext,
            new Criteria(),
        );
    }

    public function testLoadReviewDeactivated(): void
    {
        $this->expectExceptionObject(ProductException::reviewNotActive());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->exactly(1))->method('getSalesChannelId')->willReturn('testReviewNotActive');

        $this->route->load(
            Uuid::randomHex(),
            new Request(),
            $salesChannelContext,
            new Criteria(),
        );
    }
}
