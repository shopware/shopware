<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\CustomerAddressPageLoader;
use Shopware\Storefront\Page\Account\CustomerPageLoader;
use Shopware\Storefront\Page\Account\OrderPageLoader;
use Shopware\Storefront\Page\Checkout\PaymentMethodLoader;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextService;
use Shopware\StorefrontApi\Exception\PaymentMethodNotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * @Route(service="Shopware\Storefront\Controller\AccountController")
 */
class AccountController extends StorefrontController
{
    use TargetPathTrait;

    /**
     * @var AuthenticationUtils
     */
    private $authUtils;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    /**
     * @var StorefrontContextService
     */
    private $storefrontContextService;

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
        AuthenticationUtils $authUtils,
        TokenStorageInterface $tokenStorage,
        StorefrontContextPersister $contextPersister,
        AccountService $accountService,
        CustomerAddressPageLoader $customerAddressPageLoader,
        CustomerPageLoader $customerPageLoader,
        StorefrontContextService $storefrontContextService,
        PaymentMethodLoader $paymentMethodLoader,
        OrderPageLoader $orderPageLoader
    ) {
        $this->authUtils = $authUtils;
        $this->tokenStorage = $tokenStorage;
        $this->contextPersister = $contextPersister;
        $this->accountService = $accountService;
        $this->customerAddressPageLoader = $customerAddressPageLoader;
        $this->customerPageLoader = $customerPageLoader;
        $this->storefrontContextService = $storefrontContextService;
        $this->paymentMethodLoader = $paymentMethodLoader;
        $this->orderPageLoader = $orderPageLoader;
    }

    /**
     * @Route("/account", name="account_home")
     */
    public function indexAction(): Response
    {
        return $this->renderStorefront('frontend/account/index.html.twig');
    }

    /**
     * @Route("/account/login", name="account_login")
     * @Method({"GET"})
     */
    public function loginAction(Request $request, StorefrontContext $context): Response
    {
        // get the login error if there is one
        $error = $this->authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastEmail = $this->authUtils->getLastUsername();

        return $this->renderStorefront('frontend/register/index.html.twig', [
            'redirectTo' => $request->get('redirectTo', $this->generateUrl('account_home')),
            'countryList' => $this->accountService->getCountryList($context),
            'last_email' => $lastEmail,
            'error' => $error,
        ]);
    }

    /**
     * @Route("/account/login", name="account_login_check", methods={"POST"})
     */
    public function checkLoginAction()
    {
    }

    /**
     * @Route("/account/logout", name="account_logout")
     */
    public function logoutAction(StorefrontContext $context): Response
    {
        $this->contextPersister->save(
            $context->getToken(),
            [StorefrontContextService::CUSTOMER_ID => null]
        );

        return new Response('<html><body>Admin page!</body></html>');
    }

    /**
     * @Route("/account/saveRegistration", name="account_save_registration")
     * @Method({"POST"})
     */
    public function saveRegistrationAction(Request $request, StorefrontContext $context): Response
    {
        $formData = $request->request->get('register');
        // todo validate user input
        $this->accountService->createNewCustomer($formData, $context);

        $this->accountService->loginCustomer(
            $formData['personal']['email'],
            $formData['personal']['password'],
            $context
        );

        if ($targetPath = $this->getTargetPath($request->getSession(), 'storefront')) {
            return $this->redirect($targetPath);
        }

        return $this->redirectToRoute('account_home');
    }

    /**
     * @Route("/account/payment", name="account_payment", options={"seo"="false"})
     * @Method({"GET"})
     */
    public function paymentAction(Request $request, StorefrontContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/account/payment.html.twig', [
            'paymentMethods' => $this->paymentMethodLoader->load($request, $context->getShopContext()),
        ]);
    }

    /**
     * @Route("/account/savePayment", name="account_save_payment", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws UnknownPaymentMethodException
     */
    public function savePaymentAction(Request $request, StorefrontContext $context): Response
    {
        $data = $request->request->get('register');

        if (!array_key_exists('payment', $data) or !Uuid::isValid($data['payment'])) {
            throw new PaymentMethodNotFoundHttpException($data['payment']);
        }

        $this->contextPersister->save($context->getToken(), [
            StorefrontContextService::PAYMENT_METHOD_ID => $data['payment'],
        ]);

        return $this->redirectToRoute('account_home', ['success' => 'payment']);
    }

    /**
     * @Route("/account/orders", name="account_orders", options={"seo"="false"}, methods={"GET"})
     * @Method({"GET"})
     */
    public function ordersAction(Request $request, StorefrontContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/account/orders.html.twig', [
            'orderPage' => $this->orderPageLoader->load($request, $context),
        ]);
    }

    /**
     * @Route("/account/profile", name="account_profile")
     */
    public function profileAction(Request $request, StorefrontContext $context): Response
    {
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
     * @Route("/account/saveProfile", name="account_save_profile")
     * @Method({"POST"})
     */
    public function saveProfileAction(Request $request, StorefrontContext $context): Response
    {
        // todo validate user input and persist salutation
        $profile = $request->request->get('profile');
        $this->accountService->changeProfile($profile, $context);
        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        return $this->redirectToRoute('account_profile', [
            'success' => true,
            'section' => 'profile',
        ]);
    }

    /**
     * @Route("/account/savePassword", name="account_save_password", methods={"POST"})
     */
    public function savePasswordAction(Request $request, StorefrontContext $context): Response
    {
        // todo validate user input
        $password = $request->request->get('password');
        $this->accountService->changePassword($password['password'], $context);
        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        return $this->redirectToRoute('account_profile', [
            'success' => true,
            'section' => 'password',
        ]);
    }

    /**
     * @Route("/account/saveEmail", name="account_save_email", methods={"POST"})
     */
    public function saveEmailAction(Request $request, StorefrontContext $context): Response
    {
        // todo validate user input
        $email = $request->request->get('email');
        $this->accountService->changeEmail($email['email'], $context);
        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        return $this->redirectToRoute('account_profile', [
            'success' => true,
            'section' => 'email',
        ]);
    }

    /**
     * @Route("/account/address", name="address_index", options={"seo"="false"})
     * @Method({"GET"})
     */
    public function addressIndexAction(StorefrontContext $context)
    {
        return $this->renderStorefront('@Storefront/frontend/address/index.html.twig', [
            'customerAdressPage' => $this->customerAddressPageLoader->load($context),
        ]);
    }

    /**
     * @Route("/account/address/create", name="address_create", options={"seo"="false"})
     */
    public function addressCreateAction(Request $request, StorefrontContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/address/create.html.twig', [
            'countryList' => $this->accountService->getCountryList($context),
        ]);
    }

    /**
     * @Route("/account/address/save", name="address_save", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws \Shopware\CartBridge\Exception\NotLoggedInCustomerException
     */
    public function addressSaveAction(Request $request, StorefrontContext $context): Response
    {
        // todo validate user input
        $formData = $request->request->get('address');
        $addressId = $this->accountService->saveAddress($formData, $context);

        if (array_key_exists('additional', $formData)) {
            $additional = $formData['additional'];
            if (array_key_exists('setDefaultShippingAddress', $additional)) {
                $this->accountService->setDefaultShippingAddress($addressId, $context);
            }
            if (array_key_exists('setDefaultBillingAddress', $additional)) {
                $this->accountService->setDefaultBillingAddress($addressId, $context);
            }
        }
        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        if($url = $request->query->get('redirectTo')) {
            return $this->handleRedirectTo($url);
        }

        return $this->redirectToRoute('address_index');
    }

    /**
     * @Route("/account/address/edit", name="address_edit", options={"seo"="false"})
     */
    public function addressEditAction(Request $request, StorefrontContext $context): Response
    {
        $addressId = $request->query->get('addressId');
        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/edit.html.twig', [
            'formData' => $address,
            'countryList' => $this->accountService->getCountryList($context),
            'redirectTo' => $request->query->get('redirectTo'),
        ]);
    }

    /**
     * @Route("/account/address/delete_confirm", name="address_delete_confirm", options={"seo"="false"})
     * @Method({"GET"})
     */
    public function addressDeleteConfirmAction(Request $request, StorefrontContext $context): Response
    {
        $addressId = $request->query->get('addressId');
        $address = $this->accountService->getAddressById($addressId, $context);

        return $this->renderStorefront('@Storefront/frontend/address/delete.html.twig', ['address' => $address]);
    }

    /**
     * @Route("/account/address/delete", name="address_delete", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws \Shopware\CartBridge\Exception\NotLoggedInCustomerException
     */
    public function addressDeleteAction(Request $request, StorefrontContext $context): Response
    {
        $addressId = $request->request->get('addressId');
        $this->accountService->deleteAddress($addressId, $context);

        return $this->redirectToRoute('address_index', ['success' => 'delete']);
    }

    /**
     * @Route("/account/address/setDefaultBillingAddress", name="address_set_default_billing", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws \Shopware\CartBridge\Exception\NotLoggedInCustomerException
     */
    public function addressSetDefaultBillingAddressAction(Request $request, StorefrontContext $context): Response
    {
        $addressId = $request->request->get('addressId');
        $this->accountService->setDefaultBillingAddress($addressId, $context);
        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        return $this->redirectToRoute('address_index', ['success' => 'default_billing']);
    }

    /**
     * @Route("/account/address/setDefaultShippingAddress", name="address_set_default_shipping", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws \Shopware\CartBridge\Exception\NotLoggedInCustomerException
     */
    public function addressSetDefaultShippingAddressAction(Request $request, StorefrontContext $context): Response
    {
        $addressId = $request->request->get('addressId');
        $this->accountService->setDefaultShippingAddress($addressId, $context);
        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        return $this->redirectToRoute('address_index', ['success' => 'default_shipping']);
    }

    /**
     * @Route("/account/address/ajaxSelection", name="address_ajax_selection", options={"seo"="false"})
     * @Method({"GET"})
     *
     * @throws \Shopware\CartBridge\Exception\NotLoggedInCustomerException
     */
    public function addressAjaxSelectionAction(Request $request, StorefrontContext $context): Response
    {
        $addressId = $request->get('addressId');
        $setDefaultShippingAddress = (bool) $request->get('setDefaultShippingAddress', false);
        $setDefaultBillingAddress = (bool) $request->get('setDefaultBillingAddress', false);
        $addresses = $this->accountService->getAddressesByCustomer($context);

        if (!empty($addressId)) {
            /** @var CustomerAddressBasicStruct $address */
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
     * @Route("/account/address/ajaxEditor", name="address_ajax_editor", options={"seo"="false"})
     * @Method("GET")
     */
    public function addressAjaxEditAction(Request $request, StorefrontContext $context): Response
    {
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
     * @Route("/account/address/ajaxSave", name="address_ajax_save", options={"seo"="false"})
     * @Method("POST")
     *
     * @throws \Shopware\CartBridge\Exception\NotLoggedInCustomerException
     */
    public function addressAjaxSaveAction(Request $request, StorefrontContext $context): Response
    {
        // todo validate user input
        $formData = $request->request->get('address');
        $addressId = $this->accountService->saveAddress($formData, $context);

        if ($request->request->get('setDefaultShippingAddress')) {
            $this->accountService->setDefaultShippingAddress($addressId, $context);
        }
        if ($request->request->get('setDefaultBillingAddress')) {
            $this->accountService->setDefaultBillingAddress($addressId, $context);
        }

        $this->storefrontContextService->refresh($context->getShop()->getId(), $context->getToken());

        return new JsonResponse([
            'success' => true,
            'errors' => [],
            'data' => [],
        ]);
    }
}
