<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\Controller;

use Shopware\Storefront\Framework\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends StorefrontController
{
    /**
     * @Route("/", name="frontend.home.page", options={"seo"="false"}, methods={"GET"})
     *
     * @return Response
     */
    public function index(): Response
    {
        return $this->renderStorefront('@Storefront/frontend/home/index.html.twig');
    }
}
