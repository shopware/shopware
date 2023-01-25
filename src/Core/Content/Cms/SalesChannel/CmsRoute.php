<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('content')]
class CmsRoute extends AbstractCmsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader)
    {
    }

    public function getDecorated(): AbstractCmsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cms/{id}', name: 'store-api.cms.detail', methods: ['GET', 'POST'])]
    public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse
    {
        $criteria = new Criteria([$id]);

        $slots = $request->get('slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (!empty($slots)) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        $pages = $this->cmsPageLoader->load($request, $criteria, $context);

        if (!$pages->has($id)) {
            throw new PageNotFoundException($id);
        }

        return new CmsRouteResponse($pages->get($id));
    }
}
