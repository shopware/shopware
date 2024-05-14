<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\SalesChannel;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('core')]
class MediaRoute extends AbstractMediaRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository
    ) {
    }

    public function getDecorated(): AbstractMediaRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/media', name: 'store-api.media.detail', methods: ['POST'])]
    public function load(Request $request, SalesChannelContext $context): MediaRouteResponse
    {
        $ids = $request->get('ids', []);
        if (empty($ids)) {
            throw MediaException::emptyMediaId();
        }

        return new MediaRouteResponse($this->findMediaByIds($ids, $context->getContext()));
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
