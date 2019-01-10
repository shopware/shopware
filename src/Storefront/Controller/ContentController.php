<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\ContentHome\ContentHomePageLoader;
use Shopware\Storefront\Page\ContentHome\ContentHomePageRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContentController extends StorefrontController
{
    /**
     * @Route("/", name="frontend.home.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     */
    public function index(ContentHomePageRequest $request, CheckoutContext $context): ?Response
    {
        /** @var ContentHomePageLoader $indexPageLoader */
        $indexPageLoader = $this->get(ContentHomePageLoader::class);

        $data = $indexPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/home/index.html.twig', [
                'page' => $data,
            ]
        );
    }
}
