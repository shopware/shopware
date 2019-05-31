<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\Subscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrl\CanonicalUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Storefront\Framework\Seo\Subscriber\CanonicalUrlLoaderSubscriber;

class CanonicalUrlLoaderSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAddCanonicalsSalesChannelApiContext(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);

        $salesChannelId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $seoUrl = $this->createMock(SeoUrlEntity::class);
        $seoUrl->method('getForeignKey')->willReturn($productId);

        /** @var Context|MockObject $salesChannelApiContext */
        $salesChannelApiContext = $this->createMock(Context::class);
        $salesChannelApiContext
            ->method('getSource')
            ->willReturn(new SalesChannelApiSource($salesChannelId));

        $seoUrlRepo = $this->createMock(EntityRepositoryInterface::class);
        $seoUrlRepo
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    1,
                    new CanonicalUrlCollection([$seoUrl]),
                    null,
                    new Criteria(),
                    $salesChannelApiContext
                )
            );

        $registry = $this->getContainer()->get(SeoUrlRouteRegistry::class);
        $subscriber = new CanonicalUrlLoaderSubscriber($seoUrlRepo, $registry);

        /** @var ProductEntity|MockObject $product */
        $product = $this->createMock(ProductEntity::class);
        $product->expects(static::once())->method('addExtension');
        $product->method('getUniqueIdentifier')->willReturn($productId);

        $entities = [$product];
        $event = new EntityLoadedEvent($productDefinition, $entities, $salesChannelApiContext);
        $subscriber->addCanonicals($event);
    }

    public function testAddCanonicalsDefaultContext(): void
    {
        $context = Context::createDefaultContext();

        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $productId = Uuid::randomHex();

        $seoUrl = $this->createMock(SeoUrlEntity::class);
        $seoUrl->method('getForeignKey')->willReturn($productId);

        $seoUrlRepo = $this->createMock(EntityRepositoryInterface::class);
        $seoUrlRepo
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    1,
                    new CanonicalUrlCollection([$seoUrl]),
                    null,
                    new Criteria(),
                    $context
                )
            );

        $registry = $this->getContainer()->get(SeoUrlRouteRegistry::class);
        $subscriber = new CanonicalUrlLoaderSubscriber($seoUrlRepo, $registry);

        /** @var ProductEntity|MockObject $product */
        $product = $this->createMock(ProductEntity::class);
        $product->expects(static::never())->method('addExtension');
        $product->method('getUniqueIdentifier')->willReturn($productId);

        $entities = [$product];
        $event = new EntityLoadedEvent($productDefinition, $entities, $context);
        $subscriber->addCanonicals($event);
    }
}
