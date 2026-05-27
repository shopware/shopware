<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductIdentifierResolver;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Pins the UCP-id → Shopware-id resolution contract. UCP-facing item ids are
 * stable merchant identifiers (typically `productNumber`), but local
 * simulator runs occasionally pass internal UUIDs. The resolver must honour
 * both transparently — a regression here means a buyer-cart action against a
 * known product number fails with "product not found".
 *
 * @internal
 */
#[CoversClass(ProductIdentifierResolver::class)]
class ProductIdentifierResolverTest extends TestCase
{
    public function testReturnsInputWhenAlreadyAUuid(): void
    {
        $uuid = Uuid::randomHex();
        $repo = $this->createMock(SalesChannelRepository::class);
        $repo->expects($this->never())->method('search');

        $resolver = new ProductIdentifierResolver($repo);
        $resolved = $resolver->resolveToShopwareId($uuid, $this->createMock(SalesChannelContext::class));

        static::assertSame($uuid, $resolved);
    }

    public function testResolvesProductNumberThroughRepository(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('resolved-uuid');
        $product->setUniqueIdentifier('resolved-uuid');
        $collection = new SalesChannelProductCollection([$product]);

        $repo = $this->createMock(SalesChannelRepository::class);
        $repo->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                SalesChannelProductEntity::class,
                1,
                $collection,
                new AggregationResultCollection(),
                new Criteria(),
                Context::createDefaultContext()
            ));

        $resolver = new ProductIdentifierResolver($repo);
        $resolved = $resolver->resolveToShopwareId('SKU-123', $this->createMock(SalesChannelContext::class));

        static::assertSame('resolved-uuid', $resolved);
    }

    public function testReturnsNullWhenProductNumberUnknown(): void
    {
        $repo = $this->createMock(SalesChannelRepository::class);
        $repo->method('search')
            ->willReturn(new EntitySearchResult(
                SalesChannelProductEntity::class,
                0,
                new SalesChannelProductCollection(),
                new AggregationResultCollection(),
                new Criteria(),
                Context::createDefaultContext()
            ));

        $resolver = new ProductIdentifierResolver($repo);
        static::assertNull($resolver->resolveToShopwareId('does-not-exist', $this->createMock(SalesChannelContext::class)));
    }
}
