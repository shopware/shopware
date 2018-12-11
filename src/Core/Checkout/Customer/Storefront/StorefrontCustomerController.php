<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\Type\Storefront\JsonType;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Account\Exception\AddressNotFoundException;
use Shopware\Storefront\Account\Page\AccountService;
use Shopware\Storefront\Account\Page\AddressSaveRequest;
use Shopware\Storefront\Account\Page\EmailSaveRequest;
use Shopware\Storefront\Account\Page\LoginRequest;
use Shopware\Storefront\Account\Page\PasswordSaveRequest;
use Shopware\Storefront\Account\Page\ProfileSaveRequest;
use Shopware\Storefront\Account\Page\RegistrationRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class StorefrontCustomerController extends AbstractController
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var CheckoutContextService
     */
    private $checkoutContextService;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        Serializer $serializer,
        AccountService $accountService,
        CheckoutContextService $checkoutContextService,
        RepositoryInterface $orderRepository
    ) {
        $this->serializer = $serializer;
        $this->accountService = $accountService;
        $this->checkoutContextService = $checkoutContextService;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/storefront-api/customer/login", name="storefront.api.customer.login.deprecated", methods={"POST"})
     *
     * @deprecated
     */
    public function loginDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->login($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/login", name="storefront-api.customer.login", methods={"POST"})
     */
    public function login(Request $request, CheckoutContext $context): JsonResponse
    {
        $loginRequest = new LoginRequest();
        $loginRequest->assign($request->request->all());

        $token = $this->accountService->login($loginRequest, $context);

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $token,
        ]);
    }

    /**
     * @Route("/storefront-api/customer/logout", name="storefront.api.customer.logout.deprecated", methods={"POST"})
     *
     * @deprecated
     */
    public function logoutDeprecated(CheckoutContext $context): JsonResponse
    {
        return $this->logout($context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/logout", name="storefront-api.customer.logout", methods={"POST"})
     */
    public function logout(CheckoutContext $context): JsonResponse
    {
        $this->accountService->logout($context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer/orders", name="storefront.api.customer.orders.get.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function orderListDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->orderList($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/order", name="storefront-api.customer.order.list", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderList(Request $request, CheckoutContext $context): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);

        return new JsonResponse($this->serialize($this->loadOrders($page, $limit, $context)));
    }

    /**
     * @Route("/storefront-api/customer", name="storefront.api.customer.create.deprecated", methods={"POST"})
     *
     * @deprecated
     */
    public function registerDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->register($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer", name="storefront-api.customer.create", methods={"POST"})
     */
    public function register(Request $request, CheckoutContext $context): JsonResponse
    {
        $registrationRequest = new RegistrationRequest();

        $registrationRequest->assign($request->request->all());
        $registrationRequest->setGuest($request->request->getBoolean('guest'));

        $customerId = $this->accountService->createNewCustomer($registrationRequest, $context);

        return new JsonResponse($this->serialize($customerId));
    }

    /**
     * @Route("/storefront-api/customer/email", name="storefront.api.customer.email.update.deprecated", methods={"PUT"})
     *
     * @deprecated
     */
    public function saveEmailDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->saveEmail($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/email", name="storefront-api.customer.email.update", methods={"PATCH"})
     */
    public function saveEmail(Request $request, CheckoutContext $context): JsonResponse
    {
        $emailSaveRequest = new EmailSaveRequest();
        $emailSaveRequest->assign($request->request->all());

        $this->accountService->saveEmail($emailSaveRequest, $context);
        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer/password", name="storefront.api.customer.password.update.deprecated", methods={"PUT"})
     *
     * @deprecated
     */
    public function savePasswordDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->savePassword($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/password", name="storefront-api.customer.password.update", methods={"PATCH"})
     */
    public function savePassword(Request $request, CheckoutContext $context): JsonResponse
    {
        $passwordSaveRequest = new PasswordSaveRequest();
        $passwordSaveRequest->assign($request->request->all());

        if (empty($passwordSaveRequest->getPassword())) {
            return new JsonResponse($this->serialize('Invalid password'));
        }

        $this->accountService->savePassword($passwordSaveRequest, $context);
        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer/profile", name="storefront.api.customer.profile.update.deprecated", methods={"PUT"})
     *
     * @deprecated
     */
    public function saveProfileDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->saveProfile($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer", name="storefront-api.customer.update", methods={"PATCH"})
     */
    public function saveProfile(Request $request, CheckoutContext $context): JsonResponse
    {
        $profileSaveRequest = new ProfileSaveRequest();
        $profileSaveRequest->assign($request->request->all());

        $this->accountService->saveProfile($profileSaveRequest, $context);
        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer", name="storefront.api.customer.detail.get.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getCustomerDetailDeprecated(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $this->getCustomerDetail($request, $context, $responseFactory);
    }

    /**
     * @Route("/storefront-api/v{version}/customer", name="storefront-api.customer.detail", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function getCustomerDetail(Request $request, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        return $responseFactory->createDetailResponse(
            $this->accountService->getCustomerByContext($context),
            CustomerDefinition::class,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/storefront-api/customer/addresses", name="storefront.api.customer.addresses.get.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getAddressesDeprecated(CheckoutContext $context): JsonResponse
    {
        return $this->getAddresses($context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address", name="storefront-api.customer.address.list", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function getAddresses(CheckoutContext $context): JsonResponse
    {
        return new JsonResponse(
            $this->serialize($this->accountService->getAddressesByCustomer($context))
        );
    }

    /**
     * @Route("/storefront-api/customer/address/{id}", name="storefront.api.customer.address.get.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getAddressDeprecated(string $id, CheckoutContext $context): JsonResponse
    {
        return $this->getAddress($id, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address/{id}", name="storefront-api.customer.address.detail", methods={"GET"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function getAddress(string $id, CheckoutContext $context): JsonResponse
    {
        return new JsonResponse(
            $this->serialize($this->accountService->getAddressById($id, $context))
        );
    }

    /**
     * @Route("/storefront-api/customer/address", name="storefront.api.customer.address.create.deprecated", methods={"POST"})
     *
     * @deprecated
     */
    public function createAddressDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->createAddress($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address", name="storefront-api.customer.address.create", methods={"POST"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function createAddress(Request $request, CheckoutContext $context): JsonResponse
    {
        $addressSaveRequest = new AddressSaveRequest();
        $addressSaveRequest->assign($request->request->all());

        $addressId = $this->accountService->saveAddress($addressSaveRequest, $context);

        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken()
        );

        return new JsonResponse($this->serialize($addressId));
    }

    /**
     * @Route("/storefront-api/customer/address/{id}", name="storefront.api.customer.address.delete.deprecated", methods={"DELETE"})
     *
     * @deprecated
     */
    public function deleteAddressDeprecated(string $id, CheckoutContext $context): JsonResponse
    {
        return $this->deleteAddress($id, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address/{id}", name="storefront-api.customer.address.delete", methods={"DELETE"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function deleteAddress(string $id, CheckoutContext $context): JsonResponse
    {
        $this->accountService->deleteAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    /**
     * @Route("/storefront-api/customer/default-shipping-address/{id}", name="storefront.api.customer.default_shipping_address.update.deprecated", methods={"PUT"})
     */
    public function setDefaultShippingAddressDeprecated(string $id, CheckoutContext $context): JsonResponse
    {
        return $this->setDefaultShippingAddress($id, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address/{id}/default-shipping", name="storefront-api.customer.address.set-default-shipping-address", methods={"PATCH"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $id, CheckoutContext $context): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new InvalidUuidException($id);
        }
        $this->accountService->setDefaultShippingAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    /**
     * @Route("/storefront-api/customer/default-billing-address/{id}", name="storefront.api.customer.default_billing_address.update.deprecated", methods={"PUT"})
     *
     * @deprecated
     */
    public function setDefaultBillingAddressDeprecated(string $id, CheckoutContext $context): JsonResponse
    {
        return $this->setDefaultBillingAddress($id, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address/{id}/default-billing", name="storefront-api.customer.address.set-default-billing-address", methods={"PATCH"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function setDefaultBillingAddress(string $id, CheckoutContext $context): JsonResponse
    {
        $this->accountService->setDefaultBillingAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    private function loadOrders(int $page, int $limit, CheckoutContext $context): array
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        --$page;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        $criteria->addSorting(new FieldSorting('order.date', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        return $this->orderRepository->search($criteria, $context->getContext())->getElements();
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }
}
