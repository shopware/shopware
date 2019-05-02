<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException as CustomerNotLoggedInExceptionAlias;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Account\Address\AccountAddressPageLoader;
use Shopware\Storefront\Page\Account\AddressList\AccountAddressListPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountPageController extends StorefrontController
{
    /**
     * @var AccountAddressListPageLoader|PageLoaderInterface
     */
    private $addressListPageLoader;

    /**
     * @var AccountLoginPageLoader|PageLoaderInterface
     */
    private $loginPageLoader;

    /**
     * @var AccountOverviewPageLoader|PageLoaderInterface
     */
    private $overviewPageLoader;

    /**
     * @var AccountProfilePageLoader|PageLoaderInterface
     */
    private $profilePageLoader;

    /**
     * @var AccountPaymentMethodPageLoader|PageLoaderInterface
     */
    private $paymentMethodPageLoader;

    /**
     * @var AccountOrderPageLoader|PageLoaderInterface
     */
    private $orderPageLoader;

    /**
     * @var AccountAddressPageLoader|PageLoaderInterface
     */
    private $addressPageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var AccountRegistrationService
     */
    private $accountRegistrationService;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $salesChannelContextService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        PageLoaderInterface $accountLoginPageLoader,
        PageLoaderInterface $accountOverviewPageLoader,
        PageLoaderInterface $accountAddressPageLoader,
        PageLoaderInterface $accountProfilePageLoader,
        PageLoaderInterface $accountPaymentMethodPageLoader,
        PageLoaderInterface $accountOrderPageLoader,
        PageLoaderInterface $addressPageLoader,
        AccountService $accountService,
        AccountRegistrationService $accountRegistrationService,
        SalesChannelContextServiceInterface $salesChannelContextService,
        AddressService $addressService,
        TranslatorInterface $translator
    ) {
        $this->loginPageLoader = $accountLoginPageLoader;
        $this->addressListPageLoader = $accountAddressPageLoader;
        $this->overviewPageLoader = $accountOverviewPageLoader;
        $this->profilePageLoader = $accountProfilePageLoader;
        $this->paymentMethodPageLoader = $accountPaymentMethodPageLoader;
        $this->orderPageLoader = $accountOrderPageLoader;
        $this->addressPageLoader = $addressPageLoader;
        $this->accountService = $accountService;
        $this->accountRegistrationService = $accountRegistrationService;
        $this->salesChannelContextService = $salesChannelContextService;
        $this->addressService = $addressService;
        $this->translator = $translator;
    }

    /**
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->overviewPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     */
    public function login(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        /** @var string $redirect */
        $redirect = $request->get('redirectTo', 'frontend.account.home.page');

        if ($context->getCustomer()) {
            return $this->createActionResponse($request);
        }

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
            'loginError' => (bool) $request->get('loginError'),
            'data' => $data,
        ]);
    }

    /**
     * @Route("/account/logout", name="frontend.account.logout.page", methods={"GET"})
     */
    public function logout(SalesChannelContext $context): Response
    {
        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->accountService->logout($context);

            $this->addFlash('success', $this->translator->trans('account.logoutSucceeded'));

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.account.login.page', $parameters);
    }

    /**
     * @Route("/account/login", name="frontend.account.login", methods={"POST"})
     */
    public function loginCustomer(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->createActionResponse($request);
        }

        try {
            $token = $this->accountService->loginWithPassword($data, $context);
            if (!empty($token)) {
                return $this->createActionResponse($request);
            }
        } catch (BadCredentialsException | UnauthorizedHttpException $e) {
        }

        $data->set('password', null);

        return $this->forward('Shopware\Storefront\PageController\AccountPageController::login', [
            'loginError' => true,
        ]);
    }

    /**
     * @Route("/account/register", name="frontend.account.register.page", methods={"GET"})
     */
    public function register(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer() && $context->getCustomer()->getGuest()) {
            return $this->redirectToRoute('frontend.account.logout.page');
        }

        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->get('redirectParameters', json_encode([])),
            'page' => $page,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
     */
    public function saveRegister(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        try {
            if (!$data->has('differentShippingAddress')) {
                $data->remove('shippingAddress');
            }

            $this->accountRegistrationService->register($data, $data->has('guest'), $context);
        } catch (ConstraintViolationException $formViolations) {
            return $this->forward('Shopware\Storefront\PageController\AccountPageController::register', ['formViolations' => $formViolations]);
        }

        $this->accountService->login($data->get('email'), $context, $data->has('guest'));

        $redirectTo = $request->get('redirectTo', 'frontend.account.home.page');

        return $this->redirectToRoute($redirectTo);
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.page", options={"seo"="false"}, methods={"GET"})
     */
    public function paymentOverview(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->paymentMethodPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/payment/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.save", methods={"POST"})
     */
    public function savePayment(RequestDataBag $requestDataBag, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $paymentMethodId = $requestDataBag->getAlnum('paymentMethodId');

            $this->accountService->changeDefaultPaymentMethod(
                $paymentMethodId,
                $requestDataBag,
                $context->getCustomer(),
                $context->getContext()
            );
        } catch (UnknownPaymentMethodException | InvalidUuidException $exception) {
            $this->addFlash('danger', $this->translator->trans('error.' . $exception->getErrorCode()));

            return $this->forward('Shopware\Storefront\PageController\AccountPageController::paymentOverview', ['success' => false]);
        }

        $this->salesChannelContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        $this->addFlash('success', $this->translator->trans('account.paymentSuccess'));

        return new RedirectResponse($this->generateUrl('frontend.account.payment.page'));
    }

    /**
     * @Route("/account/order", name="frontend.account.order.page", options={"seo"="false"}, methods={"GET"})
     */
    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->orderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     */
    public function profileOverview(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/profile/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function saveProfile(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->saveProfile($data, $context);
            $this->salesChannelContextService->refresh(
                $context->getSalesChannel()->getId(),
                $context->getToken(),
                $context->getContext()->getLanguageId()
            );
        } catch (ConstraintViolationException $formViolations) {
            return $this->forward(__CLASS__ . '::profileOverview', ['formViolations' => $formViolations]);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/account/profile/email", name="frontend.account.profile.email.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function saveEmail(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->saveEmail($data, $context);
            $this->salesChannelContextService->refresh(
                $context->getSalesChannel()->getId(),
                $context->getToken(),
                $context->getContext()->getLanguageId()
            );
        } catch (ConstraintViolationException $formViolations) {
            return $this->forward(__CLASS__ . '::profileOverview', ['formViolations' => $formViolations]);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/account/profile/password", name="frontend.account.profile.password.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInExceptionAlias
     */
    public function savePassword(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->accountService->savePassword($data, $context);
            $this->salesChannelContextService->refresh(
                $context->getSalesChannel()->getId(),
                $context->getToken(),
                $context->getContext()->getLanguageId()
            );
        } catch (ConstraintViolationException $formViolations) {
            return $this->forward(__CLASS__ . '::profileOverview', ['formViolations' => $formViolations]);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    /**
     * @Route("/account/address", name="frontend.account.address.page", options={"seo"="false"}, methods={"GET"})
     */
    public function addressOverview(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->addressListPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/addressbook/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address/create", name="frontend.account.address.create.page", options={"seo"="false"}, methods={"GET"})
     */
    public function createAddress(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->addressPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/addressbook/create.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.page", options={"seo"="false"}, methods={"GET"})
     */
    public function editAddress(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $page = $this->addressPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/account/addressbook/edit.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/password", name="frontend.account.password.page", options={"seo"="false"}, methods={"GET","POST"})
     */
    public function password(Request $request): Response
    {
        if ($request->isMethod(Request::METHOD_POST)) {
            // @todo : send recovery email
            return $this->renderStorefront('@Storefront/page/account/password-reset/hash-sent.html.twig', ['page' => []]);
        }

        return $this->renderStorefront('@Storefront/page/account/password-reset/index.html.twig', ['page' => []]);
    }

    /**
     * @Route("/account/resetPassword/{hash}", name="frontend.account.password.reset.page", options={"seo"="false"}, methods={"GET","POST"})
     */
    public function resetPassword(Request $request): Response
    {
        // @todo verify hash and if not valid show error page with message

        if ($request->isMethod(Request::METHOD_POST)) {
            // @todo: update password, login customer and redirect to account and show success message
            return $this->redirectToRoute('frontend.account.home.page', []);
        }

        return $this->renderStorefront('@Storefront/page/account/password-reset/password-reset.html.twig', ['page' => []]);
    }

    /**
     * @Route("/account/saveNewsletter", name="frontend.account.newsletter.save", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function saveNewsletter()
    {
        $this->denyAccessUnlessLoggedIn();

        // @todo update newsletter field in customer entity

        return $this->redirectToRoute('frontend.account.home.page', []);
    }

    /**
     * @Route("/account/address/default-{type}/{addressId}", name="frontend.account.address.set-default-address", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function setDefaultShippingAddress(string $type, string $addressId, SalesChannelContext $context): RedirectResponse
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $success = true;
        try {
            if ($type === 'shipping') {
                $this->accountService->setDefaultShippingAddress($addressId, $context);
            } elseif ($type === 'billing') {
                $this->accountService->setDefaultBillingAddress($addressId, $context);
            } else {
                $success = false;
            }
        } catch (AddressNotFoundException $exception) {
            $success = false;
        }

        $this->salesChannelContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        return new RedirectResponse($this->generateUrl('frontend.account.address.page', [
            'changedDefaultAddress' => $success,
        ]));
    }

    /**
     * @Route("/account/address/delete/{addressId}", name="frontend.account.address.delete", options={"seo"="false"}, methods={"POST"})
     */
    public function deleteAddress(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $success = true;
        $addressId = $request->request->get('addressId');

        if (!$addressId) {
            throw new MissingRequestParameterException('addressId');
        }

        try {
            $this->addressService->delete($addressId, $context);
        } catch (InvalidUuidException | AddressNotFoundException | CannotDeleteDefaultAddressException $exception) {
            $success = false;
        }

        return new RedirectResponse($this->generateUrl('frontend.account.address.page', ['addressDeleted' => $success]));
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.save", options={"seo"="false"}, methods={"POST"})
     * @Route("/account/address/create", name="frontend.account.address.create", options={"seo"="false"}, methods={"POST"})
     */
    public function saveAddress(RequestDataBag $data, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        /** @var RequestDataBag $address */
        $address = $data->get('address');
        try {
            $this->addressService->create($address, $context);

            return new RedirectResponse($this->generateUrl('frontend.account.address.page', ['addressSaved' => true]));
        } catch (ConstraintViolationException $formViolations) {
        }

        $forwardAction = $address->get('id') ? 'editAddress' : 'createAddress';

        return $this->forward(
            'Shopware\Storefront\PageController\AccountPageController::' . $forwardAction,
            ['formViolations' => $formViolations],
            ['addressId' => $address->get('id')]
        );
    }
}
