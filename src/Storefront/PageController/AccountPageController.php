<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException as CustomerNotLoggedInExceptionAlias;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Account\Address\AccountAddressPageLoader;
use Shopware\Storefront\Page\Account\AddressList\AccountAddressListPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountPageController extends StorefrontController
{
    /**
     * @var AccountAddressListPageLoader
     */
    private $addressListPageLoader;

    /**
     * @var AccountLoginPageLoader
     */
    private $loginPageLoader;

    /**
     * @var AccountOverviewPageLoader
     */
    private $overviewPageLoader;

    /**
     * @var AccountProfilePageLoader
     */
    private $profilePageLoader;

    /**
     * @var AccountPaymentMethodPageLoader
     */
    private $paymentMethodPageLoader;

    /**
     * @var AccountOrderPageLoader
     */
    private $orderPageLoader;

    /**
     * @var AccountAddressPageLoader
     */
    private $addressPageLoader;

    public function __construct(
        AccountLoginPageLoader $accountLoginPageLoader,
        AccountOverviewPageLoader $accountOverviewPageLoader,
        AccountAddressListPageLoader $accountAddressPageLoader,
        AccountProfilePageLoader $accountProfilePageLoader,
        AccountPaymentMethodPageLoader $accountPaymentMethodPageLoader,
        AccountOrderPageLoader $accountOrderPageLoader,
        AccountAddressPageLoader $addressPageLoader
    ) {
        $this->loginPageLoader = $accountLoginPageLoader;
        $this->addressListPageLoader = $accountAddressPageLoader;
        $this->overviewPageLoader = $accountOverviewPageLoader;
        $this->profilePageLoader = $accountProfilePageLoader;
        $this->paymentMethodPageLoader = $accountPaymentMethodPageLoader;
        $this->orderPageLoader = $accountOrderPageLoader;
        $this->addressPageLoader = $addressPageLoader;
    }

    /**
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function index(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->overviewPageLoader->load($request, $context);

        return $this->renderStorefront('frontend/account/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     */
    public function login(InternalRequest $request, CheckoutContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $redirect = $request->optionalGet('redirectTo', $this->generateUrl('frontend.account.home.page'));

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('frontend/register/index.html.twig', ['redirectTo' => $redirect, 'page' => $page]);
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function paymentOverview(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->paymentMethodPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/account/payment.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/order", name="frontend.account.order.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function orderOverview(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->orderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/account/orders.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function profileOverview(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/account/profile.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address", name="frontend.account.address.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function addressOverview(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->addressListPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/address/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address/create", name="frontend.account.address.create.page", options={"seo"="false"}, methods={"GET"})
     */
    public function createAddress(InternalRequest $request, CheckoutContext $context): Response
    {
        $page = $this->addressPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/address/create.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function editAddress(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->addressPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/address/edit.html.twig', ['page' => $page]);
    }
}
