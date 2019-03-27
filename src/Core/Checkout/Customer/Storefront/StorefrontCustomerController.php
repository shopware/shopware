<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextServiceInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\Type\Storefront\JsonType;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
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
     * @var AccountRegistrationService
     */
    private $accountRegisterService;

    /**
     * @var CheckoutContextServiceInterface
     */
    private $checkoutContextService;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AddressService
     */
    private $addressService;

    public function __construct(
        Serializer $serializer,
        AccountService $accountService,
        CheckoutContextServiceInterface $checkoutContextService,
        EntityRepositoryInterface $orderRepository,
        AccountRegistrationService $accountRegisterService,
        AddressService $addressService
    ) {
        $this->serializer = $serializer;
        $this->accountService = $accountService;
        $this->checkoutContextService = $checkoutContextService;
        $this->orderRepository = $orderRepository;
        $this->accountRegisterService = $accountRegisterService;
        $this->addressService = $addressService;
    }

    /**
     * @Route("/storefront-api/v{version}/customer/login", name="storefront-api.customer.login", methods={"POST"})
     */
    public function login(RequestDataBag $requestData, CheckoutContext $context): JsonResponse
    {
        $token = $this->accountService->loginWithPassword($requestData, $context);

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $token,
        ]);
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
     * @Route("/storefront-api/v{version}/customer", name="storefront-api.customer.create", methods={"POST"})
     */
    public function register(RequestDataBag $requestData, CheckoutContext $context): JsonResponse
    {
        $isGuest = $requestData->getBoolean('guest');

        $customerId = $this->accountRegisterService->register($requestData, $isGuest, $context);

        return new JsonResponse($this->serialize($customerId));
    }

    /**
     * @Route("/storefront-api/v{version}/customer/email", name="storefront-api.customer.email.update", methods={"PATCH"})
     */
    public function saveEmail(RequestDataBag $requestData, CheckoutContext $context): JsonResponse
    {
        $this->accountService->saveEmail($requestData, $context);

        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/v{version}/customer/password", name="storefront-api.customer.password.update", methods={"PATCH"})
     */
    public function savePassword(RequestDataBag $requestData, CheckoutContext $context): JsonResponse
    {
        $this->accountService->savePassword($requestData, $context);

        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/v{version}/customer", name="storefront-api.customer.update", methods={"PATCH"})
     */
    public function saveProfile(RequestDataBag $requestData, CheckoutContext $context): JsonResponse
    {
        $this->accountService->saveProfile($requestData, $context);
        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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
     * @Route("/storefront-api/v{version}/customer/address", name="storefront-api.customer.address.list", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function getAddresses(CheckoutContext $context): JsonResponse
    {
        return new JsonResponse(
            $this->serialize($this->addressService->getAddressByContext($context))
        );
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
            $this->serialize($this->addressService->getById($id, $context))
        );
    }

    /**
     * @Route("/storefront-api/v{version}/customer/address", name="storefront-api.customer.address.create", methods={"POST"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function createAddress(RequestDataBag $requestData, CheckoutContext $context): JsonResponse
    {
        $addressId = $this->addressService->create($requestData, $context);

        $this->checkoutContextService->refresh(
            $context->getSalesChannel()->getId(),
            $context->getToken(),
            $context->getContext()->getLanguageId()
        );

        return new JsonResponse($this->serialize($addressId));
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
        $this->addressService->delete($id, $context);

        return new JsonResponse($this->serialize($id));
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
        $criteria->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING));
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
