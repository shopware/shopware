<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductDescriptionReviewsTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ProductDescriptionReviewsCmsElementResolver $productDescriptionReviewResolver;

    protected function setUp(): void
    {
        $productReviewLoaderMock = $this->createMock(ProductReviewLoader::class);
        $productReviewLoaderMock->method('load')->willReturn(
            ProductReviewResult::createFrom(
                new EntitySearchResult('product_review', 0, new ProductReviewCollection(), null, new Criteria(), Context::createDefaultContext())
            )
        );

        $scriptExecutorMock = $this->createMock(ScriptExecutor::class);

        $this->productDescriptionReviewResolver = new ProductDescriptionReviewsCmsElementResolver(
            $productReviewLoaderMock,
            $scriptExecutorMock
        );
    }

    public function testType(): void
    {
        static::assertSame(ProductDescriptionReviewsCmsElementResolver::TYPE, $this->productDescriptionReviewResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(ProductDescriptionReviewsCmsElementResolver::TYPE);

        $collection = $this->productDescriptionReviewResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(ProductDescriptionReviewsCmsElementResolver::TYPE);

        $this->productDescriptionReviewResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductDescriptionReviewsStruct|null $productDescriptionReviewStruct */
        $productDescriptionReviewStruct = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $productDescriptionReviewStruct);
        static::assertNull($productDescriptionReviewStruct->getProduct());
    }
}
