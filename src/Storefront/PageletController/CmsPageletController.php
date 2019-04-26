<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Cms\CmsPagelet;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CmsPageletController extends StorefrontController
{
    /**
     * @var PageLoaderInterface
     */
    private $cmsPageletLoader;

    public function __construct(PageLoaderInterface $cmsPageletLoader)
    {
        $this->cmsPageletLoader = $cmsPageletLoader;
    }

    /**
     * @Route("/widget/cms/{id}", name="widgets.cms.detail", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function detail(Request $request, SalesChannelContext $context): Response
    {
        /** @var CmsPagelet $page */
        $page = $this->cmsPageletLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/content/detail.html.twig', ['page' => $page]);
    }
}
