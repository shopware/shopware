<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\LandingPage\Exception\LandingPageNotFoundException;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('content')]
class LandingPageRoute extends AbstractLandingPageRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelRepository $landingPageRepository,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly LandingPageDefinition $landingPageDefinition
    ) {
    }

    public function getDecorated(): AbstractLandingPageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/landing-page/{landingPageId}', name: 'store-api.landing-page.detail', methods: ['POST'])]
    public function load(string $landingPageId, Request $request, SalesChannelContext $context): LandingPageRouteResponse
    {
        $landingPage = $this->loadLandingPage($landingPageId, $context);

        $pageId = $landingPage->getCmsPageId();

        if (!$pageId) {
            return new LandingPageRouteResponse($landingPage);
        }

        $resolverContext = new EntityResolverContext($context, $request, $this->landingPageDefinition, $landingPage);

        $pages = $this->cmsPageLoader->load(
            $request,
            $this->createCriteria($pageId, $request),
            $context,
            $landingPage->getTranslation('slotConfig'),
            $resolverContext
        );

        if (!$pages->has($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $landingPage->setCmsPage($pages->get($pageId));

        return new LandingPageRouteResponse($landingPage);
    }

    private function loadLandingPage(string $landingPageId, SalesChannelContext $context): LandingPageEntity
    {
        $criteria = new Criteria([$landingPageId]);
        $criteria->setTitle('landing-page::data');

        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannel()->getId()));

        $landingPage = $this->landingPageRepository
            ->search($criteria, $context)
            ->get($landingPageId);

        if (!$landingPage) {
            throw new LandingPageNotFoundException($landingPageId);
        }

        return $landingPage;
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('landing-page::cms-page');

        $slots = $request->get('slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (!empty($slots) && \is_array($slots)) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        return $criteria;
    }
}
