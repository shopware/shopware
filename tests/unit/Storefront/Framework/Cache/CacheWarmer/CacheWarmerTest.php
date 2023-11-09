<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache\CacheWarmer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmerRegistry;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\Product\ProductRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer
 */
class CacheWarmerTest extends TestCase
{
    private EntityRepository&MockObject $salesChannelDomainRepositoryMock;

    private MessageBusInterface&MockObject $busMock;

    private CacheRouteWarmerRegistry&MockObject $registryMock;

    private CacheIdLoader&MockObject $cacheIdLoader;

    private CacheWarmer $cacheWarmer;

    private ProductRouteWarmer&MockObject $productRouteWarmerMock;

    protected function setUp(): void
    {
        $this->salesChannelDomainRepositoryMock = $this->createMock(EntityRepository::class);
        $this->busMock = $this->createMock(MessageBusInterface::class);
        $this->registryMock = $this->createMock(CacheRouteWarmerRegistry::class);
        $this->cacheIdLoader = $this->createMock(CacheIdLoader::class);
        $this->productRouteWarmerMock = $this->createMock(ProductRouteWarmer::class);

        $this->cacheWarmer = new CacheWarmer(
            $this->salesChannelDomainRepositoryMock,
            $this->busMock,
            $this->registryMock,
            $this->cacheIdLoader
        );
    }

    public function testWarmUpNoId(): void
    {
        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setId(Uuid::randomHex());
        $salesChannelDomain->setUniqueIdentifier(Uuid::randomHex());
        $salesChannelDomain->setUrl('https://localhost');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $cacheId = Uuid::randomHex();
        $this->cacheIdLoader->expects(static::once())->method('load')->willReturn($cacheId);

        $warmUpMessage = new WarmUpMessage('/testRoute', []);
        $warmUpMessageExpected = new WarmUpMessage('/testRoute', []);
        $warmUpMessageExpected->setCacheId($cacheId);
        $warmUpMessageExpected->setDomain('https://localhost');

        $criteria->addFilter(
            new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
        );

        $this->productRouteWarmerMock->expects(static::exactly(2))->method('createMessage')
            ->willReturnOnConsecutiveCalls($warmUpMessage, null);

        $this->registryMock->expects(static::once())->method('getWarmers')
            ->willReturn([$this->productRouteWarmerMock]);

        $this->salesChannelDomainRepositoryMock->expects(static::once())->method('search')
            ->with($criteria, $context)
            ->willReturn(
                new EntitySearchResult(
                    SalesChannelDomainDefinition::ENTITY_NAME,
                    1,
                    new SalesChannelDomainCollection([$salesChannelDomain]),
                    null,
                    $criteria,
                    $context
                )
            );

        $this->busMock->expects(static::once())->method('dispatch')->with($warmUpMessageExpected)
            ->willReturn(new Envelope($this->createMock(\stdClass::class)));

        $this->cacheWarmer->warmUp();
    }

    public function testWarmUpWithId(): void
    {
        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setId(Uuid::randomHex());
        $salesChannelDomain->setUniqueIdentifier(Uuid::randomHex());
        $salesChannelDomain->setUrl('https://localhost');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $cacheId = Uuid::randomHex();
        $this->cacheIdLoader->expects(static::never())->method('load');

        $warmUpMessage = new WarmUpMessage('/testRoute', []);
        $warmUpMessageExpected = new WarmUpMessage('/testRoute', []);
        $warmUpMessageExpected->setCacheId($cacheId);
        $warmUpMessageExpected->setDomain('https://localhost');

        $criteria->addFilter(
            new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
        );

        $this->productRouteWarmerMock->expects(static::exactly(2))->method('createMessage')
            ->willReturnOnConsecutiveCalls($warmUpMessage, null);

        $this->registryMock->expects(static::once())->method('getWarmers')
            ->willReturn([$this->productRouteWarmerMock]);

        $this->salesChannelDomainRepositoryMock->expects(static::once())->method('search')
            ->with($criteria, $context)
            ->willReturn(
                new EntitySearchResult(
                    SalesChannelDomainDefinition::ENTITY_NAME,
                    1,
                    new SalesChannelDomainCollection([$salesChannelDomain]),
                    null,
                    $criteria,
                    $context
                )
            );

        $this->busMock->expects(static::once())->method('dispatch')->with($warmUpMessageExpected)
            ->willReturn(new Envelope($this->createMock(\stdClass::class)));

        $this->cacheWarmer->warmUp($cacheId);
    }
}
