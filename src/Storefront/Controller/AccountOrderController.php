<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractAccountOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AccountOrderController extends StorefrontController
{
    /**
     * @var AccountOrderPageLoader
     */
    private $orderPageLoader;

    /**
     * @var AbstractAccountOrderRoute
     */
    private $orderRoute;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    public function __construct(
        AccountOrderPageLoader $orderPageLoader,
        AbstractAccountOrderRoute $orderRoute,
        RequestCriteriaBuilder $requestCriteriaBuilder
    ) {
        $this->orderPageLoader = $orderPageLoader;
        $this->orderRoute = $orderRoute;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
    }

    /**
     * @Route("/account/order", name="frontend.account.order.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->orderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/widgets/account/order/detail/{id}", name="widgets.account.order.detail", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function ajaxOrderDetail(Request $request, SalesChannelContext $context)
    {
        $this->denyAccessUnlessLoggedIn();

        $orderId = (string) $request->get('id');

        if ($orderId === '') {
            throw new MissingRequestParameterException('id');
        }

        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('lineItems')
            ->addAssociation('orderCustomer')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems.cover');

        $orderRequest = new Request();
        $orderRequest->query->replace($this->requestCriteriaBuilder->toArray($criteria));

        $order = $this->orderRoute->load($orderRequest, $context)->getOrders()->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }
        $lineItems = $order->getNestedLineItems();

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/order-detail-list.html.twig', ['orderDetails' => $lineItems, 'orderId' => $orderId]);
    }
}
