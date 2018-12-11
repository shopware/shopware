<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Controller\Widget;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Listing\NavigationService;
use Shopware\Storefront\Seo\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NavigationController extends StorefrontController
{
    /**
     * @Route("/widgets/navigation/navigation", name="widgets/navigation/main", methods={"GET"})
     *
     * @param CheckoutContext $context
     *
     * @return null|Response
     */
    public function navigationAction(CheckoutContext $context): ?Response
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getMasterRequest();

        if (!$request) {
            return null;
        }

        $navigationId = $this->getNavigationId($request);

        /** @var NavigationService $navigationService */
        $navigationService = $this->get(NavigationService::class);
        $navigation = $navigationService->load($navigationId, $context->getContext());

        return $this->render('@Storefront/widgets/navigation/navigation.html.twig', [
            'navigation' => $navigation,
        ]);
    }

    /**
     * @Route("/widgets/navigation/sidebar", name="widgets/navigation/sidebar", methods={"GET"})
     *
     * @param CheckoutContext $context
     *
     * @return null|Response
     */
    public function sidebarAction(CheckoutContext $context): ?Response
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getMasterRequest();

        if (!$request) {
            return null;
        }

        $navigationId = $this->getNavigationId($request);

        /** @var NavigationService $navigationService */
        $navigationService = $this->get(NavigationService::class);
        $navigation = $navigationService->load($navigationId, $context->getContext());

        return $this->render('@Storefront/widgets/navigation/sidebar.html.twig', [
            'navigation' => $navigation,
        ]);
    }

    private function getNavigationId(Request $request): ?string
    {
        $route = $request->attributes->get('_route');

        switch ($route) {
            case ListingPageSeoUrlIndexer::ROUTE_NAME:
                return $request->attributes->get('_route_params')['id'];
        }

        return null;
    }
}
