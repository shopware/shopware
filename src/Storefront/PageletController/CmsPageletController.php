<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
     * @Route("/widgets/cms/{id}", name="widgets.cms.page", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function page(string $id, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->load($id, $request, $context);

        return $this->renderStorefront('@Storefront/page/content/detail.html.twig', ['page' => $page->get($id)]);
    }

    /**
     * Route to load a cms page which assigned to the provided category id.
     * Category id is required to load the slot config for the category
     *
     * @Route("/widgets/cms/category/{categoryId}", name="widgets.cms.category.page", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function category(string $categoryId, Request $request, SalesChannelContext $context): Response
    {
        $categories = $this->categoryRepository->search(new Criteria([$categoryId]), $context);

        if (!$categories->has($categoryId)) {
            throw new CategoryNotFoundException($categoryId);
        }

        /** @var CategoryEntity $category */
        $category = $categories->get($categoryId);

        if (!$category->getCmsPageId()) {
            throw new PageNotFoundException('');
        }

        $page = $this->load($category->getCmsPageId(), $request, $context, $category->getSlotConfig());

        return $this->renderStorefront('@Storefront/page/content/detail.html.twig', ['page' => $page->get($id)]);
    }

    private function load(string $id, Request $request, SalesChannelContext $context, ?array $config = null)
    {
        $criteria = new Criteria([$id]);

        $slots = json_decode((string) $request->get('slots'), true);

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