<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminProductStreamController;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturn(new EntitySearchResult(
                'product',
                1,
                $collection,
                null,
                new Criteria(),
                $context
            ));

        $response = $controller->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"extensions":[],"elements":[],"aggregations":[],"page":1,"limit":null,"entity":"product","total":1,"states":[]}',
            $response->getContent()
        );
    }

    public function testProductStreamPreviewWithVisibilityFilter(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $collection = new ProductCollection();

        // Mock criteria builder to return criteria with visibility filter
        $criteriaWithVisibilityFilter = new Criteria();
        $criteriaWithVisibilityFilter->addFilter(new EqualsFilter('product.visibilities.salesChannelId', 'sales-channel-id'));

        $this->requestCriteriaBuilder->expects(static::once())
            ->method('handleRequest')
            ->willReturn($criteriaWithVisibilityFilter);

        $this->salesChannelRepository->expects(static::once())->method('search')
            ->with(static::callback(function (Criteria $criteria) {
                // Verify that no additional ProductAvailableFilter was added when visibility filter is present
                $filters = $criteria->getFilters();
                $hasVisibilityFilter = false;
                $hasProductAvailableFilter = false;

                foreach ($filters as $filter) {
                    if (in_array('product.visibilities.salesChannelId', $filter->getFields(), true)) {
                        $hasVisibilityFilter = true;
                    }
                    if ($filter instanceof \Shopware\Core\Content\Product\SalesChannel\Listing\ProductAvailableFilter) {
                        $hasProductAvailableFilter = true;
                    }
                }

                return $hasVisibilityFilter && !$hasProductAvailableFilter;
            }))
            ->willReturn(new EntitySearchResult(
                'product',
                1,
                $collection,
                null,
                new Criteria(),
                $context
            ));

        $response = $controller->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
    }

    public function testProductStreamPreviewWithoutVisibilityFilter(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $collection = new ProductCollection();

        // Mock criteria builder to return criteria without visibility filter
        $criteriaWithoutVisibilityFilter = new Criteria();

        $this->requestCriteriaBuilder->expects(static::once())
            ->method('handleRequest')
            ->willReturn($criteriaWithoutVisibilityFilter);

        $this->salesChannelRepository->expects(static::once())->method('search')
            ->with(static::callback(function (Criteria $criteria) {
                // Verify that ProductAvailableFilter was added when no visibility filter is present
                $filters = $criteria->getFilters();
                $hasProductAvailableFilter = false;

                foreach ($filters as $filter) {
                    if ($filter instanceof \Shopware\Core\Content\Product\SalesChannel\Listing\ProductAvailableFilter) {
                        $hasProductAvailableFilter = true;
                    }
                }

                return $hasProductAvailableFilter;
            }))
            ->willReturn(new EntitySearchResult(
                'product',
                1,
                $collection,
                null,
                new Criteria(),
                $context
            ));

        $response = $controller->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
    }
}
