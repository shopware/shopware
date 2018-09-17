<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Symfony\Component\Routing\Annotation\Route;

class IndexController extends StorefrontController
{
    /**
     * @Route("/", name="homepage", options={"seo"="false"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        return $this->renderStorefront('@Storefront/frontend/home/index.html.twig');
    }
}
