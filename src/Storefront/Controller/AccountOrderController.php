<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    public function __construct(AccountOrderPageLoader $orderPageLoader, $orderRepository)
    {
        $this->orderPageLoader = $orderPageLoader;
        $this->orderRepository = $orderRepository;
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

        $customerId = $context->getCustomer()->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId))
            ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId))
            ->addAssociation('lineItems')
            ->addAssociation('orderCustomer')
            ->addAssociation('lineItems.cover');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }
        $lineItems = $order->getNestedLineItems();

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/order-detail-list.html.twig', ['orderDetails' => $lineItems, 'orderId' => $orderId]);
    }
}
