<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\SalesChannel\MediaRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MediaRoute::class)]
class MediaRouteTest extends TestCase
{
    private EntityRepository&MockObject $mediaRepository;

    private MediaRoute $mediaRoute;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->mediaRoute = new MediaRoute($this->mediaRepository);
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
            ->expects(static::once())
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

        $this->mediaRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($mediaEntitySearchResult);

        $response = $this->mediaRoute->load($request, $salesChannelContext);
        $mediaCollection = $response->getMediaCollection();
        $firstMediaEntity = $mediaCollection->first();

        static::assertCount(2, $mediaCollection);
        static::assertInstanceOf(MediaEntity::class, $firstMediaEntity);
        static::assertSame('testMediaId1', $firstMediaEntity->getId());
        static::assertSame('testPath1', $firstMediaEntity->getPath());
    }

    public function testLoadThrowsMediaExceptionWhenMediaNotFound(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A media id must be provided.');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::never())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $request = new Request([], ['ids' => '']);

        $this->mediaRoute->load($request, $salesChannelContext);
    }
}
