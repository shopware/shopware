<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\LandingPage\Exception\LandingPageNotFoundException;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class LandingPageRoute extends AbstractLandingPageRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $landingPageRepository;

    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    /**
     * @var LandingPageDefinition
     */
    private $landingPageDefinition;

    public function __construct(
        SalesChannelRepositoryInterface $landingPageRepository,
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        LandingPageDefinition $landingPageDefinition
    ) {
        $this->landingPageRepository = $landingPageRepository;
        $this->cmsPageLoader = $cmsPageLoader;
        $this->landingPageDefinition = $landingPageDefinition;
    }

    public function getDecorated(): AbstractLandingPageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.4.0.0")
     * @OA\Post(
     *      path="/landing-page/{landingPageId}",
     *      summary="Fetch a landing page with the resolved CMS page",
     *      description="Loads a landing page by its identifier and resolves the CMS page.

**Important notice**

The criteria passed with this route also affects the listing, if there is one within the cms page.",
     *      operationId="readLandingPage",
     *      tags={"Store API", "Content"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="landingPageId",
     *          description="Identifier of the landing page.",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(
     *                      description="The product listing criteria only has an effect, if the landing page contains a product listing.",
     *                      ref="#/components/schemas/ProductListingCriteria"
     *                  ),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          property="slots",
     *                          description="Resolves only the given slot identifiers. The identifiers have to be seperated by a `|` character.",
     *                          type="string"
     *                      )
     *                  )
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The loaded landing page with cms page",
     *          @OA\JsonContent(ref="#/components/schemas/LandingPage")
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     ),
     * )
     *
     * @Route("/store-api/landing-page/{landingPageId}", name="store-api.landing-page.detail", methods={"POST"})
     */
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
