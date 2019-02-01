<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Home\HomePageLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomePageController extends StorefrontController
{
    /**
     * @var HomePageLoader
     */
    private $homePageLoader;

    public function __construct(HomePageLoader $homePageLoader)
    {
        $this->homePageLoader = $homePageLoader;
    }

    /**
     * @Route("/", name="frontend.home.page", options={"seo"="false"}, methods={"GET"})
     *
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @return Response|null
     */
    public function index(InternalRequest $request, CheckoutContext $context): ?Response
    {
        $data = $this->homePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/index/index.html.twig', ['page' => $data]);
    }
}
