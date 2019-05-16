<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountOrderController extends StorefrontController
{
    /**
     * @var AccountOrderPageLoader
     */
    private $orderPageLoader;

    public function __construct(AccountOrderPageLoader $orderPageLoader)
    {
        $this->orderPageLoader = $orderPageLoader;
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

        return $this->renderStorefront('@Storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }
}
