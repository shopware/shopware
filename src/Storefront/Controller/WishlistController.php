<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRemoveWishlistProductRoute;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class WishlistController extends StorefrontController
{
    /**
     * @var WishlistPageLoader
     */
    private $wishlistPageLoader;

    /**
     * @var AbstractRemoveWishlistProductRoute
     */
    private $removeWishlistProductRoute;

    public function __construct(
        WishlistPageLoader $wishlistPageLoader,
        AbstractRemoveWishlistProductRoute $removeWishlistProductRoute
    ) {
        $this->wishlistPageLoader = $wishlistPageLoader;
        $this->removeWishlistProductRoute = $removeWishlistProductRoute;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/wishlist", name="frontend.wishlist.page", methods={"GET"})
     */
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->wishlistPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/wishlist/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/widgets/wishlist", name="widgets.wishlist.pagelet", methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function ajaxPagination(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $request->request->set('no-aggregations', true);

        $page = $this->wishlistPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/wishlist/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/wishlist/product/delete/{id}", name="frontend.wishlist.product.delete", methods={"POST", "DELETE"}, defaults={"XmlHttpRequest"=true})
     */
    public function remove(string $id, Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        if (!$id) {
            throw new MissingRequestParameterException('Parameter id missing');
        }

        try {
            $this->removeWishlistProductRoute->delete($id, $context);

            $this->addFlash('success', $this->trans('wishlist.itemDeleteSuccess'));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }
}
