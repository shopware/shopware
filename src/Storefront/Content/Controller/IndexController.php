<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Content\PageLoader\IndexPageLoader;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends StorefrontController
{
    /**
     * @Route("/", name="frontend.home.page", options={"seo"="false"}, methods={"GET"})
     */
    public function index(CheckoutContext $context, PageRequest $request): ?Response
    {
        /** @var IndexPageLoader $indexPageLoader */
        $indexPageLoader = $this->get(IndexPageLoader::class);

        $data = $indexPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/home/index.html.twig', $data->toArray());
    }
}
