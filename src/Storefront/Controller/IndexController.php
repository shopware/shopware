<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends StorefrontController
{
    /**
     * @Route("/", name="homepage", options={"seo"="false"})
     *
     * @return Response
     */
    public function index(): Response
    {
        return $this->renderStorefront('@Storefront/frontend/home/index.html.twig');
    }
}
