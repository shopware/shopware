<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Page\Detail\DetailPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DetailController extends StorefrontController
{
    /**
     * @var DetailPageLoader
     */
    private $detailPageLoader;

    public function __construct(DetailPageLoader $detailPageLoader)
    {
        $this->detailPageLoader = $detailPageLoader;
    }

    /**
     * @Route("/detail/{id}", name="frontend.detail.page", options={"seo"="true"}, methods={"GET"})
     */
    public function index(string $id, CheckoutContext $context, Request $request): Response
    {
        $page = $this->detailPageLoader->load($id, $request, $context);

        $xhr = $request->isXmlHttpRequest();
        $template = '@Storefront/frontend/detail/index.html.twig';

        if ($xhr) {
            $template = '@Storefront/frontend/detail/content.html.twig';
        }

        return $this->renderStorefront($template, ['page' => $page]);
    }
}
