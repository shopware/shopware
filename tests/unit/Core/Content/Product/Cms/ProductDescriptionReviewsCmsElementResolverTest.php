<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(ProductDescriptionReviewsCmsElementResolver::class)]
class ProductDescriptionReviewsCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = $this->getResolver();

        static::assertSame('product-description-reviews', $resolver->getType());
    }

    public function testEnrichSlotWithProductDescriptionReviews(): void
    {
        $resolver = $this->getResolver();

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request([
            'success' => true,
        ]));

        $productId = 'product-1';
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $result = $this->createMock(EntitySearchResult::class);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result->method('get')
            ->with($productId)
            ->willReturn($product);

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        $reviews = $data->getReviews();
        static::assertInstanceOf(ProductReviewResult::class, $reviews);
        static::assertSame($product, $data->getProduct());
        static::assertSame($productId, $reviews->getProductId());
    }

    public function testEnrichSetsEmptyDataWithoutConfig(): void
    {
        $resolver = $this->getResolver();

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertNull($data->getReviews());
        static::assertNull($data->getProduct());
    }

    private function getResolver(): ProductDescriptionReviewsCmsElementResolver
    {
        $productReviewLoader = new ProductReviewLoader(
            $this->createMock(AbstractProductReviewRoute::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(EventDispatcherInterface::class)
        );
        $scriptExecutor = $this->createMock(ScriptExecutor::class);

        return new ProductDescriptionReviewsCmsElementResolver($productReviewLoader, $scriptExecutor);
    }
}
