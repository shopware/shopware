<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminProductStreamController;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminProductStreamController::class)]
class AdminProductStreamControllerTest extends TestCase
{
    private MockObject&RequestCriteriaBuilder $requestCriteriaBuilder;

    private MockObject&SalesChannelContextServiceInterface $salesChannelContextService;

    /** @var MockObject&SalesChannelRepository<ProductCollection> */
    private MockObject&SalesChannelRepository $salesChannelRepository;

    private MockObject&ProductDefinition $productDefinition;

    protected function setUp(): void
    {
        $this->productDefinition = $this->createMock(ProductDefinition::class);
        $this->salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $this->requestCriteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
    }

    public function testProductStreamPreview(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $collection = new ProductCollection();

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $context) use ($collection) {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($criteria->hasAssociation('options'));
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(ProductAvailableFilter::class, $criteria->getFilters()[0]);

                return new EntitySearchResult(
                    'product',
                    1,
                    $collection,
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"extensions":[],"elements":[],"aggregations":[],"page":1,"limit":null,"entity":"product","total":1,"states":[]}',
            $response->getContent()
        );
    }
}
