<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Storefront\AccountService;
use Shopware\Core\Checkout\Payment\Exception\PaymentMethodNotFoundException;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Action\AccountAddress\AddressSaveRequest;
use Shopware\Storefront\Action\AccountEmail\EmailSaveRequest;
use Shopware\Storefront\Action\AccountLogin\LoginRequest;
use Shopware\Storefront\Action\AccountPassword\PasswordSaveRequest;
use Shopware\Storefront\Action\AccountProfile\ProfileSaveRequest;
use Shopware\Storefront\Action\AccountRegistration\RegistrationRequest;
use Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException;
use Shopware\Storefront\Exception\AccountLogin\CustomerNotFoundException;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Exception\BadCredentialsException;
use Shopware\Storefront\Page\AccountAddress\AccountAddressPageLoader;
use Shopware\Storefront\Page\AccountAddress\AccountAddressPageRequest;
use Shopware\Storefront\Page\AccountLogin\AccountLoginPageLoader;
use Shopware\Storefront\Page\AccountLogin\LoginPageRequest;
use Shopware\Storefront\Page\AccountOrder\AccountOrderPageLoader;
use Shopware\Storefront\Page\AccountOrder\AccountOrderPageRequest;
use Shopware\Storefront\Page\AccountOverview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\AccountOverview\AccountOverviewPageRequest;
use Shopware\Storefront\Page\AccountPaymentMethod\AccountPaymentMethodPageLoader;
use Shopware\Storefront\Page\AccountPaymentMethod\AccountPaymentMethodPageRequest;
use Shopware\Storefront\Page\AccountProfile\AccountProfilePageLoader;
use Shopware\Storefront\Page\AccountProfile\AccountProfilePageRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountController extends StorefrontController
{
    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var CheckoutContextService
     */
    private $checkoutContextService;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var AccountAddressPageLoader
     */
    private $accountAddressPageLoader;

    /**
     * @var AccountLoginPageLoader
     */
    private $accountLoginPageLoader;

    /**
     * @var AccountOverviewPageLoader
     */
    private $accountOverviewPageLoader;

    /**
     * @var AccountProfilePageLoader
     */
    private $accountProfilePageLoader;

    /**
     * @var AccountPaymentMethodPageLoader
     */
    private $accountPaymentMethodPageLoader;

    /**
     * @var AccountOrderPageLoader
     */
    private $accountOrderPageLoader;

    public function __construct(
        CheckoutContextPersister $contextPersister,
        AccountService $accountService,
        AccountLoginPageLoader $accountLoginPageLoader,
        AccountOverviewPageLoader $accountOverviewPageLoader,
        AccountAddressPageLoader $accountAddressPageLoader,
        AccountProfilePageLoader $accountProfilePageLoader,
        AccountPaymentMethodPageLoader $accountPaymentMethodPageLoader,
        AccountOrderPageLoader $accountOrderPageLoader,
        CheckoutContextService $checkoutContextService
    ) {
        $this->contextPersister = $contextPersister;
        $this->accountService = $accountService;
        $this->accountLoginPageLoader = $accountLoginPageLoader;
        $this->accountAddressPageLoader = $accountAddressPageLoader;
        $this->accountOverviewPageLoader = $accountOverviewPageLoader;
        $this->accountProfilePageLoader = $accountProfilePageLoader;
        $this->accountPaymentMethodPageLoader = $accountPaymentMethodPageLoader;
        $this->accountOrderPageLoader = $accountOrderPageLoader;
        $this->checkoutContextService = $checkoutContextService;
    }

    /**
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function index(AccountOverviewPageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->accountOverviewPageLoader->load($request, $context);

        return $this->renderStorefront('frontend/account/index.html.twig', $page->toArray());
    }

    /**
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function login(LoginPageRequest $request, CheckoutContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $page = $this->accountLoginPageLoader->load($request, $context);

        if (empty($page->getRedirectTo())) {
            $page->setRedirectTo($this->generateUrl('frontend.account.home.page'));
        }

        return $this->renderStorefront('frontend/register/index.html.twig', [
            'redirectTo' => $page->getRedirectTo(),
            'countryList' => $this->accountService->getCountryList($context),
        ]);
    }

    /**
     * @Route("/account/login", name="frontend.account.login.check", methods={"POST"})
     */
    public function checkLogin(LoginRequest $loginRequest, Request $request, CheckoutContext $context): RedirectResponse
    {
        try {
            $customer = $this->accountService->getCustomerByLogin(
                $loginRequest->getUsername(),
                $loginRequest->getPassword(),
                $context
            );
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            $this->addFlash('login_failure', 'Invalid credentials.');

            return $this->redirectToRoute('frontend.account.login.page');
        }

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::CUSTOMER_ID => $customer->getId()]
        );

        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        if ($url = $request->query->get('redirectTo')) {
            return $this->handleRedirectTo($url);
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    /**
     * @Route("/account/logout", name="frontend.account.logout", methods={"POST","GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function logout(CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::CUSTOMER_ID => null]
        );

        return $this->redirectToRoute('frontend.home.page');
    }

    /**
     * @Route("/account/register", name="frontend.account.registration.save", methods={"POST"})
     */
    public function register(RegistrationRequest $registrationRequest, Request $request, CheckoutContext $context): Response
    {
        try {
            // todo validate user input
            $customerId = $this->accountService->createNewCustomer($registrationRequest, $context);

            $this->contextPersister->save(
                $context->getToken(),
                [CheckoutContextService::CUSTOMER_ID => $customerId]
            );

            $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());
        } catch (\Exception $exception) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function paymentOverview(AccountPaymentMethodPageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->accountPaymentMethodPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/account/payment.html.twig', $page->toArray());
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.switch", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws PaymentMethodNotFoundException
     */
    public function switchPayment(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $data = $request->request->get('register');

        if (!array_key_exists('payment', $data) || !Uuid::isValid($data['payment'])) {
            throw new PaymentMethodNotFoundException($data['payment']);
        }

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::PAYMENT_METHOD_ID => $data['payment']]
        );

        return $this->redirectToRoute('frontend.account.home.page', ['success' => 'payment']);
    }

    /**
     * @Route("/account/order", name="frontend.account.order.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function orderOverview(AccountOrderPageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->accountOrderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/account/orders.html.twig', $page->toArray());
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function profileOverview(AccountProfilePageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo implement salutation
        // todo handle error messages

        $page = $this->accountProfilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/account/profile.html.twig', $page->toArray());
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.update", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function updateProfile(ProfileSaveRequest $profileSaveRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->saveProfile($profileSaveRequest, $context);

        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken()
        );

        return $this->redirectToRoute('frontend.account.profile.page', [
            'success' => true,
            'section' => 'profile',
        ]);
    }

    /**
     * @Route("/account/password", name="frontend.account.password.update", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function updatePassword(PasswordSaveRequest $passwordSaveRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $this->accountService->savePassword($passwordSaveRequest, $context);
        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        return $this->redirectToRoute('frontend.account.profile.page', [
            'success' => true,
            'section' => 'password',
        ]);
    }

    /**
     * @Route("/account/email", name="frontend.account.email.update", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function updateEmail(EmailSaveRequest $emailSaveRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $this->accountService->saveEmail($emailSaveRequest, $context);
        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        return $this->redirectToRoute('frontend.account.profile.page', [
            'success' => true,
            'section' => 'email',
        ]);
    }

    /**
     * @Route("/account/address", name="frontend.account.address.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function addressOverview(AccountAddressPageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->accountAddressPageLoader->load($request, $context)->toArray();

        return $this->renderStorefront('@Storefront/frontend/address/index.html.twig', $page);
    }

    /**
     * @Route("/account/address/create", name="frontend.account.address.create.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function createAddress(AccountAddressPageRequest $request, CheckoutContext $context): Response
    {
        $page = array_merge(
            $this->accountAddressPageLoader->load($request, $context)->toArray(),
            [
                'countryList' => $this->accountService->getCountryList($context),
            ]
        );

        return $this->renderStorefront('@Storefront/frontend/address/create.html.twig', $page);
    }

    /**
     * @Route("/account/address", name="frontend.account.address.upsert", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function upsertAddress(AddressSaveRequest $request, Request $httpRequest, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->saveAddress($request, $context);

        if ($request->isDefaultBillingAddress()) {
            $this->accountService->setDefaultShippingAddress($request->getId(), $context);
        }
        if ($request->isDefaultShippingAddress()) {
            $this->accountService->setDefaultBillingAddress($request->getId(), $context);
        }

        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        if ($url = $httpRequest->query->get('redirectTo')) {
            return $this->handleRedirectTo($url);
        }

        return $this->redirectToRoute('frontend.account.address.page');
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function editAddress($addressId, AccountAddressPageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/edit.html.twig', [
            'formData' => $address,
            'countryList' => $this->accountService->getCountryList($context),
            'redirectTo' => $request->getRedirectTo(),
        ]);
    }

    /**
     * @Route("/account/address/{addressId}/delete-confirm", name="frontend.account.address.delete-confirm.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws \Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException
     */
    public function deleteAddressConfirm(AccountAddressPageRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->getAddressRequest()->getAddressId();
        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/delete.html.twig', ['address' => $address]);
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.delete", options={"seo"="false"}, methods={"DELETE"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function deleteAddress($addressId, Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->deleteAddress($addressId, $context);

        return $this->redirectToRoute('frontend.account.address.page', ['success' => 'delete']);
    }

    /**
     * @Route("/account/address/{addressId}/default-billing", name="frontend.account.address.set-default-billing", options={"seo"="false"}, methods={"PATCH"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultBillingAddress(string $addressId, Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->setDefaultBillingAddress($addressId, $context);
        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        return $this->redirectToRoute('frontend.account.address.page', ['success' => 'default_billing']);
    }

    /**
     * @Route("/account/address/{addressId}/default-shipping", name="frontend.account.address.set-default-shipping", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $addressId, Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->setDefaultShippingAddress($addressId, $context);
        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        return $this->redirectToRoute('frontend.account.address.page', ['success' => 'default_shipping']);
    }

    /**
     * @Route("/ajax/account/address", name="frontend.account.address.list.ajax", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function ajaxAddressList(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $setDefaultShippingAddress = (bool) $request->get('setDefaultShippingAddress', false);
        $setDefaultBillingAddress = (bool) $request->get('setDefaultBillingAddress', false);
        $addresses = $this->accountService->getAddressesByCustomer($context);

        if (!empty($request->get('addressId'))) {
            /** @var CustomerAddressEntity $address */
            foreach ($addresses as $key => $address) {
                if ($address->getId() === $request->get('addressId')) {
                    unset($addresses[$key]);
                }
            }
        }

        return $this->renderStorefront('@Storefront/frontend/address/ajax_selection.html.twig', [
            'addresses' => $addresses,
            'activeAddressId' => $request->get('addressId'),
            'setDefaultBillingAddress' => $setDefaultBillingAddress,
            'setDefaultShippingAddress' => $setDefaultShippingAddress,
        ]);
    }

    /**
     * @Route("/ajax/account/address/edit", name="frontend.account.address.edit.ajax", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function addressAjaxEdit(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->query->get('addressId', '');
        if ($addressId) {
            $address = $this->accountService->getAddressById($addressId, $context);
        }

        return $this->renderStorefront('@Storefront/frontend/address/ajax_editor.html.twig', [
            'formData' => $address ?? null,
            'countryList' => $this->accountService->getCountryList($context),
        ]);
    }

    /**
     * @Route("/ajax/account/address", name="frontend.account.address.upsert.ajax", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws \Exception
     */
    public function addressAjaxSave(AddressSaveRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $addressId = $this->accountService->saveAddress($request, $context);

        if ($request->isDefaultShippingAddress()) {
            $this->accountService->setDefaultShippingAddress($addressId, $context);
        }
        if ($request->isDefaultBillingAddress()) {
            $this->accountService->setDefaultBillingAddress($addressId, $context);
        }

        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        return new JsonResponse([
            'success' => true,
            'errors' => [],
            'data' => [],
        ]);
    }
}
