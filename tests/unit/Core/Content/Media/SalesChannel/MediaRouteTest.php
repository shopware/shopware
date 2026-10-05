<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Extension\MediaRouteExtension;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\SalesChannel\MediaRoute;
use Shopware\Core\Content\Media\SalesChannel\MediaRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaRoute::class)]
class MediaRouteTest extends TestCase
{
    /**
     * @var EntityRepository<MediaCollection>&Stub
     */
    private EntityRepository&Stub $mediaRepository;

    private CacheTagCollector&Stub $cacheTagCollector;

    private MediaRoute $mediaRoute;

    protected function setUp(): void
    {
        $this->mediaRepository = static::createStub(EntityRepository::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->mediaRoute = new MediaRoute(
            $this->mediaRepository,
            $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }

    public function testLoadReturnsMediaRouteResponse(): void
    {
        $ids = ['testMediaId1', 'testMediaId2'];

        $mediaEntity1 = new MediaEntity();
        $mediaEntity1->setId('testMediaId1');
        $mediaEntity1->setPath('testPath1');

        $mediaEntity2 = new MediaEntity();
        $mediaEntity2->setId('testMediaId2');
        $mediaEntity2->setPath('testPath2');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $request = new Request([], ['ids' => $ids]);

        $mediaEntitySearchResult = new EntitySearchResult(
            'media',
            2,
            new MediaCollection([$mediaEntity1, $mediaEntity2]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($mediaEntitySearchResult);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with('media-testMediaId1', 'media-testMediaId2');

        $mediaRoute = new MediaRoute($mediaRepository, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));

        $response = $mediaRoute->load($request, $salesChannelContext);
        $mediaCollection = $response->getMediaCollection();
        $firstMediaEntity = $mediaCollection->first();

        static::assertCount(2, $mediaCollection);
        static::assertInstanceOf(MediaEntity::class, $firstMediaEntity);
        static::assertSame('testMediaId1', $firstMediaEntity->getId());
        static::assertSame('testPath1', $firstMediaEntity->getPath());
    }

    public function testLoadThrowsMediaExceptionWhenMediaNotFound(): void
    {
        $this->expectExceptionObject(MediaException::emptyMediaId());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->never())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $request = new Request([], ['ids' => '']);

        $this->mediaRoute->load($request, $salesChannelContext);
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = static::createStub(MediaRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('media-route.load.pre', static function (MediaRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new MediaRoute(
            static::createStub(EntityRepository::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context));
    }
}
