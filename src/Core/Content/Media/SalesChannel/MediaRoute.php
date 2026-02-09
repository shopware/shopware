<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\SalesChannel;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class MediaRoute extends AbstractMediaRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'media-' . $id;
    }

    public function getDecorated(): AbstractMediaRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/media',
        name: 'store-api.media.detail',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, SalesChannelContext $context): MediaRouteResponse
    {
        $ids = RequestParamHelper::get($request, 'ids', []);
        if (empty($ids)) {
            throw MediaException::emptyMediaId();
        }

        $mediaCollection = $this->findMediaByIds($ids, $context->getContext());

        $tags = [];
        foreach ($mediaCollection as $media) {
            $tags[] = self::buildName($media->getId());
        }
        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }

        return new MediaRouteResponse($mediaCollection);
    }

    /**
     * @param array<string> $ids
     */
    private function findMediaByIds(array $ids, Context $context): MediaCollection
    {
        $criteria = new Criteria($ids);
        $criteria->addFilter(new EqualsFilter('private', false));

        $mediaSearchResult = $this->mediaRepository
            ->search($criteria, $context);

        return $mediaSearchResult->getEntities();
    }
}
