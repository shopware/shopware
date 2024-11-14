<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\DuplicateWishlistProductException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractAddWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractMergeWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRemoveWishlistProductRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoadedHook;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoadedHook;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Shopware\Storefront\Page\Wishlist\WishListPageProductCriteriaEvent;
use Shopware\Storefront\Page\Wishlist\WishlistWidgetLoadedHook;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoadedHook;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class WishlistController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WishlistPageLoader $wishlistPageLoader,
        private readonly AbstractLoadWishlistRoute $wishlistLoadRoute,
        private readonly AbstractAddWishlistProductRoute $addWishlistRoute,
        private readonly AbstractRemoveWishlistProductRoute $removeWishlistProductRoute,
        private readonly AbstractMergeWishlistProductRoute $mergeWishlistProductRoute,
        private readonly GuestWishlistPageLoader $guestPageLoader,
        private readonly GuestWishlistPageletLoader $guestPageletLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route(path: '/wishlist', name: 'frontend.wishlist.page', options: ['seo' => false], defaults: ['_noStore' => true], methods: ['GET'])]
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();

        if ($customer !== null && $customer->getGuest() === false) {
            $page = $this->wishlistPageLoader->load($request, $context, $customer);
            $this->hook(new WishlistPageLoadedHook($page, $context));
        } else {
            $page = $this->guestPageLoader->load($request, $context);
            $this->hook(new GuestWishlistPageLoadedHook($page, $context));
        }

        return $this->renderStorefront('@Storefront/storefront/page/wishlist/index.html.twig', ['page' => $page]);
    }

    #[Route(path: '/wishlist/guest-pagelet', name: 'frontend.wishlist.guestPage.pagelet', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function guestPagelet(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();

        if ($customer !== null && $customer->getGuest() === false) {
            throw new NotFoundHttpException();
        }

        $pagelet = $this->guestPageletLoader->load($request, $context);
        $this->hook(new GuestWishlistPageletLoadedHook($pagelet, $context));

        return $this->renderStorefront(
            '@Storefront/storefront/page/wishlist/wishlist-pagelet.html.twig',
            ['page' => $pagelet, 'searchResult' => $pagelet->getSearchResult()->getObject()]
        );
    }

    #[Route(path: '/widgets/wishlist', name: 'widgets.wishlist.pagelet', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['GET', 'POST'])]
    public function ajaxPagination(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->wishlistPageLoader->load($request, $context, $customer);
        $this->hook(new WishlistPageLoadedHook($page, $context));

        $response = $this->renderStorefront('@Storefront/storefront/page/wishlist/index.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(path: '/wishlist/list', name: 'frontend.wishlist.product.list', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['GET'])]
    public function ajaxList(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $criteria = new Criteria();
        $criteria->setTitle('wishlist::list');
        $this->eventDispatcher->dispatch(new WishListPageProductCriteriaEvent($criteria, $context, $request));

        try {
            $res = $this->wishlistLoadRoute->load($request, $context, $criteria, $customer);
        } catch (CustomerWishlistNotFoundException) {
            return new JsonResponse([]);
        }

        return new JsonResponse($res->getProductListing()->getIds());
    }

    #[Route(path: '/wishlist/product/delete/{id}', name: 'frontend.wishlist.product.delete', defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST', 'DELETE'])]
    public function remove(string $id, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        try {
            $this->removeWishlistProductRoute->delete($id, $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('wishlist.itemDeleteSuccess'));
        } catch (\Throwable) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/wishlist/add/{productId}', name: 'frontend.wishlist.product.add', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function ajaxAdd(string $productId, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $this->addWishlistRoute->add($productId, $context, $customer);
            $success = true;
        } catch (\Throwable) {
            $success = false;
        }

        return new JsonResponse([
            'success' => $success,
        ]);
    }

    #[Route(path: '/wishlist/remove/{productId}', name: 'frontend.wishlist.product.remove', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function ajaxRemove(string $productId, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $this->removeWishlistProductRoute->delete($productId, $context, $customer);
            $success = true;
        } catch (\Throwable) {
            $success = false;
        }

        return new JsonResponse([
            'success' => $success,
        ]);
    }

    #[Route(path: '/wishlist/add-after-login/{productId}', name: 'frontend.wishlist.add.after.login', options: ['seo' => false], defaults: ['_loginRequired' => true], methods: ['GET'])]
    public function addAfterLogin(string $productId, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->addWishlistRoute->add($productId, $context, $customer);

            $this->addFlash(self::SUCCESS, $this->trans('wishlist.itemAddedSuccess'));
        } catch (DuplicateWishlistProductException) {
            $this->addFlash(self::WARNING, $this->trans('wishlist.duplicateItemError'));
        } catch (\Throwable) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.home.page');
    }

    #[Route(path: '/wishlist/merge', name: 'frontend.wishlist.product.merge', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function ajaxMerge(RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        try {
            $this->mergeWishlistProductRoute->merge($requestDataBag, $context, $customer);

            return $this->renderStorefront('@Storefront/storefront/utilities/alert.html.twig', [
                'type' => 'info', 'content' => $this->trans('wishlist.wishlistMergeHint'),
            ]);
        } catch (\Throwable) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/wishlist/merge/pagelet', name: 'frontend.wishlist.product.merge.pagelet', defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['GET', 'POST'])]
    public function ajaxPagelet(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->wishlistPageLoader->load($request, $context, $customer);
        $this->hook(new WishlistWidgetLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/wishlist/wishlist-pagelet.html.twig', [
            'page' => $page,
            'searchResult' => $page->getWishlist()->getProductListing(),
        ]);
    }
}
