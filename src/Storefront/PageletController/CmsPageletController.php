<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CmsPageletController extends StorefrontController
{
    /**
     * @var SalesChannelCmsPageLoader
     */
    private $cmsPageLoader;

    /**
     * @var SalesChannelRepository
     */
    private $categoryRepository;

    public function __construct(SalesChannelCmsPageLoader $cmsPageLoader, SalesChannelRepository $categoryRepository)
    {
        $this->cmsPageLoader = $cmsPageLoader;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Route for stand alone cms pages
     *
     * @Route("/widgets/cms/{id}", name="widgets.cms.page", methods={"GET", "POST"}, defaults={"id"=null, "XmlHttpRequest"=true})
     */
    public function page(string $id, Request $request, SalesChannelContext $context): Response
    {
        if (!$id) {
            throw new MissingRequestParameterException('Parameter id missing');
        }

        $page = $this->load($id, $request, $context);

        return $this->renderStorefront('@Storefront/page/content/detail.html.twig', ['cmsPage' => $page]);
    }

    /**
     * Route to load a cms page which assigned to the provided navigation id.
     * Navigation id is required to load the slot config for the navigation
     *
     * @Route("/widgets/cms/navigation/{navigationId}", name="widgets.cms.navigation.page", methods={"GET", "POST"}, defaults={"navigationId"=null, "XmlHttpRequest"=true})
     */
    public function category(string $navigationId, Request $request, SalesChannelContext $context): Response
    {
        if (!$navigationId) {
            throw new MissingRequestParameterException('Parameter navigationId missing');
        }

        $categories = $this->categoryRepository->search(new Criteria([$navigationId]), $context);

        if (!$categories->has($navigationId)) {
            throw new CategoryNotFoundException($navigationId);
        }

        /** @var CategoryEntity $category */
        $category = $categories->get($navigationId);

        if (!$category->getCmsPageId()) {
            throw new PageNotFoundException('');
        }

        $page = $this->load($category->getCmsPageId(), $request, $context, $category->getSlotConfig());

        return $this->renderStorefront('@Storefront/page/content/detail.html.twig', ['cmsPage' => $page]);
    }

    private function load(string $id, Request $request, SalesChannelContext $context, ?array $config = null): ?CmsPageEntity
    {
        $criteria = new Criteria([$id]);

        $slots = $request->get('slots');

        if (!empty($slots)) {
            $criteria->addFilter(new EqualsAnyFilter('cms_page.blocks.slots.id', $slots));
        }

        $pages = $this->cmsPageLoader->load($request, $criteria, $context, $config);

        if (!$pages->has($id)) {
            throw new PageNotFoundException($id);
        }

        return $pages->get($id);
    }
}
