<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminProductStreamController;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
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

    private Stub&SalesChannelContextServiceInterface $salesChannelContextService;

    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private MockObject&SalesChannelRepository $salesChannelRepository;

    private Stub&ProductDefinition $productDefinition;

    private MockObject&ProductListingLoader $productListingLoader;

    protected function setUp(): void
    {
        $this->productDefinition = static::createStub(ProductDefinition::class);
        $this->salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $this->salesChannelContextService = static::createStub(SalesChannelContextServiceInterface::class);
        $this->requestCriteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $this->productListingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testProductStreamPreview(): void
    {
        $context = Context::createDefaultContext();

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());
        $this->productListingLoader->expects($this->never())->method('load');

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $context) {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($criteria->hasAssociation('options'));
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertFalse($criteria->hasState(ProductListingLoader::STATE_INCLUDE_INACTIVE));
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(ProductAvailableFilter::class, $criteria->getFilters()[0]);
                static::assertCount(0, $criteria->getGroupFields());

                return new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $this->createController()->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"extensions":[],"elements":[],"aggregations":[],"page":1,"limit":null,"entity":"product","total":1,"states":[]}',
            $response->getContent()
        );
    }

    public function testGroupedProductStreamPreviewUsesListingLoader(): void
    {
        $context = Context::createDefaultContext();

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());
        $this->salesChannelRepository->expects($this->never())->method('search');

        $this->productListingLoader->expects($this->once())->method('load')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $context) {
                static::assertTrue($criteria->hasState(ProductListingLoader::STATE_INCLUDE_INACTIVE));
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(ProductAvailableFilter::class, $criteria->getFilters()[0]);
                // the loader applies the variant grouping itself
                static::assertCount(0, $criteria->getGroupFields());

                return new EntitySearchResult(
                    'product',
                    3,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $this->createController()->productStreamPreview(
            'salesChannelId',
            new Request(['displayAsGroup' => '1']),
            $context
        );

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(3, $content['total']);
    }

    private function createController(): AdminProductStreamController
    {
        return new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
            $this->productListingLoader,
        );
    }
}
