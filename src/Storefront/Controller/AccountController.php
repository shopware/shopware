<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Payment\Exception\PaymentMethodNotFoundException;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Exception\AddressNotFoundException;
use Shopware\Storefront\Exception\BadCredentialsException;
use Shopware\Storefront\Exception\CustomerNotFoundException;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\AddressSaveRequest;
use Shopware\Storefront\Page\Account\CustomerAddressPageLoader;
use Shopware\Storefront\Page\Account\CustomerPageLoader;
use Shopware\Storefront\Page\Account\EmailSaveRequest;
use Shopware\Storefront\Page\Account\LoginRequest;
use Shopware\Storefront\Page\Account\OrderPageLoader;
use Shopware\Storefront\Page\Account\PasswordSaveRequest;
use Shopware\Storefront\Page\Account\ProfileSaveRequest;
use Shopware\Storefront\Page\Account\RegistrationRequest;
use Shopware\Storefront\Page\Checkout\PaymentMethodLoader;
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
     * @var PaymentMethodLoader
     */
    private $paymentMethodLoader;

    /**
     * @var OrderPageLoader
     */
    private $orderPageLoader;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var CustomerAddressPageLoader
     */
    private $customerAddressPageLoader;

    /**
     * @var CustomerPageLoader
     */
    private $customerPageLoader;

    public function __construct(
        CheckoutContextPersister $contextPersister,
        AccountService $accountService,
        CustomerAddressPageLoader $customerAddressPageLoader,
        CustomerPageLoader $customerPageLoader,
        CheckoutContextService $checkoutContextService,
        PaymentMethodLoader $paymentMethodLoader,
        OrderPageLoader $orderPageLoader
    ) {
        $this->contextPersister = $contextPersister;
        $this->accountService = $accountService;
        $this->customerAddressPageLoader = $customerAddressPageLoader;
        $this->customerPageLoader = $customerPageLoader;
        $this->checkoutContextService = $checkoutContextService;
        $this->paymentMethodLoader = $paymentMethodLoader;
        $this->orderPageLoader = $orderPageLoader;
    }

    /**
     * @Route("/account", name="frontend.account.home.page", methods={"GET"})
     */
    public function index(): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('frontend/account/index.html.twig');
    }

    /**
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     */
    public function login(Request $request, CheckoutContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        return $this->renderStorefront('frontend/register/index.html.twig', [
            'redirectTo' => $request->get('redirectTo', $this->generateUrl('frontend.account.home.page')),
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
     * @Route("/account/logout", name="frontend.account.logout", methods={"POST"})
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
        } catch (BadCredentialsException $exception) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.page", options={"seo"="false"}, methods={"GET"})
     */
    public function paymentOverview(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/account/payment.html.twig', [
            'paymentMethods' => $this->paymentMethodLoader->load($request, $context->getContext()),
        ]);
    }

    /**
     * @Route("/account/payment", name="frontend.account.payment.switch", options={"seo"="false"}, methods={"POST"})
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
     */
    public function orderOverview(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/account/orders.html.twig', [
            'orderPage' => $this->orderPageLoader->load($request, $context),
        ]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.page", methods={"GET"})
     */
    public function profileOverview(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo implement salutation
        // todo handle error messages
//        if ($request->query->get('errors')) {
//            foreach ($request->query->get('errors') as $error) {
//                $message = $this->View()->fetch('string:' . $error->getMessage());
//                $errorFlags[$error->getOrigin()->getName()] = true;
//                $errorMessages[] = $message;
//            }
//
//            $errorMessages = array_unique($errorMessages);
//        }

        return $this->renderStorefront('@Storefront/frontend/account/profile.html.twig', [
            'customerPage' => $this->customerPageLoader->load($context),
            'formData' => $request->request->get('formData', []),
            'errorFlags' => [],
            'errorMessages' => [],
            'success' => $request->query->get('success'),
            'section' => $request->query->get('section'),
        ]);
    }

    /**
     * @Route("/account/profile", name="frontend.account.profile.update", methods={"POST"})
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
     */
    public function addressOverview(CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/address/index.html.twig', [
            'customerAdressPage' => $this->customerAddressPageLoader->load($context),
        ]);
    }

    /**
     * @Route("/account/address", name="frontend.account.address.create.page", options={"seo"="false"}, methods={"GET"})
     */
    public function createAddress(CheckoutContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/address/create.html.twig', [
            'countryList' => $this->accountService->getCountryList($context),
        ]);
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
     * @Route("/account/address/{addressId}", name="frontend.account.address.edit.page", options={"seo"="false"})
     */
    public function editAddress($addressId, Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/edit.html.twig', [
            'formData' => $address,
            'countryList' => $this->accountService->getCountryList($context),
            'redirectTo' => $request->query->get('redirectTo'),
        ]);
    }

    /**
     * @Route("/account/address/{addressId}/delete-confirm", name="frontend.account.address.delete-confirm.page", options={"seo"="false"}, methods={"GET"})
     */
    public function deleteAddressConfirm(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $addressId = $request->query->get('addressId');
        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/delete.html.twig', ['address' => $address]);
    }

    /**
     * @Route("/account/address/{addressId}", name="frontend.account.address.delete", options={"seo"="false"}, methods={"DELETE"})
     *
     * @throws CustomerNotLoggedInException
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
     */
    public function setDefaultBillingAddress(string $addressId, Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->accountService->setDefaultBillingAddress($addressId, $context);
        $this->checkoutContextService->refresh($context->getSalesChannel()->getId(), $context->getToken());

        return $this->redirectToRoute('frontend.account.address.page', ['success' => 'default_billing']);
    }

    /**
     * @Route("/account/address/{addressId}/default-shipping", name="frontend.account.address.set-default-shipping", options={"seo"="false"}, methods={"PATCH"})
     *
     * @throws CustomerNotLoggedInException
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
    public function ajaxAddressList(string $addressId, Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $setDefaultShippingAddress = (bool) $request->get('setDefaultShippingAddress', false);
        $setDefaultBillingAddress = (bool) $request->get('setDefaultBillingAddress', false);
        $addresses = $this->accountService->getAddressesByCustomer($context);

        if (!empty($addressId)) {
            /** @var CustomerAddressStruct $address */
            foreach ($addresses as $key => $address) {
                if ($address->getId() === $addressId) {
                    unset($addresses[$key]);
                }
            }
        }

        return $this->renderStorefront('@Storefront/frontend/address/ajax_selection.html.twig', [
            'addresses' => $addresses,
            'activeAddressId' => $addressId,
            'setDefaultBillingAddress' => $setDefaultBillingAddress,
            'setDefaultShippingAddress' => $setDefaultShippingAddress,
        ]);
    }

    /**
     * @Route("/ajax/account/address/edit", name="frontend.account.address.edit.ajax", options={"seo"="false"}, methods={"GET"})
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
     */
    public function addressAjaxSave(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        // todo validate user input
        $formData = $request->request->get('address');
        $addressId = $this->accountService->saveAddress($formData, $context);

        if ($request->request->get('setDefaultShippingAddress')) {
            $this->accountService->setDefaultShippingAddress($addressId, $context);
        }
        if ($request->request->get('setDefaultBillingAddress')) {
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
