<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Home\IndexPageLoader;
use Shopware\Storefront\Page\Home\IndexPageRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContentController extends StorefrontController
{
    /**
     * @Route("/", name="frontend.home.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     * @throws \Twig_Error_Loader
     */
    public function index(IndexPageRequest $request, CheckoutContext $context): ?Response
    {
        /** @var IndexPageLoader $indexPageLoader */
        $indexPageLoader = $this->get(IndexPageLoader::class);

        $data = $indexPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/home/index.html.twig', $data->toArray());
    }
}
