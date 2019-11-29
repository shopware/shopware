<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelCustomerController extends AbstractController
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
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var CustomerDefinition
     */
    private $customerDefinition;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var CustomerAddressDefinition
     */
    private $addressDefinition;

    public function __construct(
        Serializer $serializer,
        AccountService $accountService,
        EntityRepositoryInterface $orderRepository,
        AccountRegistrationService $accountRegisterService,
        AddressService $addressService,
        CustomerDefinition $customerDefinition,
        CustomerAddressDefinition $addressDefinition,
        ApiVersionConverter $apiVersionConverter
    ) {
        $this->serializer = $serializer;
        $this->accountService = $accountService;
        $this->orderRepository = $orderRepository;
        $this->accountRegisterService = $accountRegisterService;
        $this->addressService = $addressService;
        $this->customerDefinition = $customerDefinition;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->addressDefinition = $addressDefinition;
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/login", name="sales-channel-api.customer.login", methods={"POST"})
     */
    public function login(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $token = $this->accountService->loginWithPassword($requestData, $context);

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $token,
        ]);
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/logout", name="sales-channel-api.customer.logout", methods={"POST"})
     */
    public function logout(SalesChannelContext $context): JsonResponse
    {
        $this->accountService->logout($context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/order", name="sales-channel-api.customer.order.list", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderList(int $version, Request $request, SalesChannelContext $context): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);

        $orders = $this->loadOrders($page, $limit, $context);
        $convertedOrders = [];
        foreach ($orders as $order) {
            $convertedOrders[] = $this->apiVersionConverter->convertEntity(
                $this->orderRepository->getDefinition(),
                $order,
                $version
            );
        }

        return new JsonResponse($this->serialize($convertedOrders));
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer", name="sales-channel-api.customer.create", methods={"POST"})
     */
    public function register(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $isGuest = $requestData->getBoolean('guest');

        $customerId = $this->accountRegisterService->register($requestData, $isGuest, $context);

        return new JsonResponse($this->serialize($customerId));
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/email", name="sales-channel-api.customer.email.update", methods={"PATCH"})
     */
    public function saveEmail(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $this->accountService->saveEmail($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/password", name="sales-channel-api.customer.password.update", methods={"PATCH"})
     */
    public function savePassword(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $this->accountService->savePassword($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer", name="sales-channel-api.customer.update", methods={"PATCH"})
     */
    public function saveProfile(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $this->accountService->saveProfile($requestData, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer", name="sales-channel-api.customer.detail", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function getCustomerDetail(Request $request, SalesChannelContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            throw new CustomerNotLoggedInException();
        }

        return $responseFactory->createDetailResponse(
            new Criteria(),
            $customer,
            $this->customerDefinition,
            $request,
            $context->getContext()
        );
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/address", name="sales-channel-api.customer.address.list", methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function getAddresses(int $version, SalesChannelContext $context): JsonResponse
    {
        $addresses = $this->addressService->getAddressByContext($context);
        $converted = [];

        foreach ($addresses as $address) {
            $converted[] = $this->apiVersionConverter->convertEntity($this->addressDefinition, $address, $version);
        }

        return new JsonResponse(
            $this->serialize($converted)
        );
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/address/{id}", name="sales-channel-api.customer.address.detail", methods={"GET"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function getAddress(string $id, int $version, SalesChannelContext $context): JsonResponse
    {
        $address = $this->addressService->getById($id, $context);

        return new JsonResponse(
            $this->serialize($this->apiVersionConverter->convertEntity($this->addressDefinition, $address, $version))
        );
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/address", name="sales-channel-api.customer.address.create", methods={"POST", "PATCH"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function upsertAddress(RequestDataBag $requestData, SalesChannelContext $context): JsonResponse
    {
        $addressId = $this->addressService->upsert($requestData, $context);

        return new JsonResponse($this->serialize($addressId));
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/address/{id}", name="sales-channel-api.customer.address.delete", methods={"DELETE"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function deleteAddress(string $id, SalesChannelContext $context): JsonResponse
    {
        $this->addressService->delete($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/address/{id}/default-shipping", name="sales-channel-api.customer.address.set-default-shipping-address", methods={"PATCH"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $id, SalesChannelContext $context): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new InvalidUuidException($id);
        }
        $this->accountService->setDefaultShippingAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    /**
     * @Route("/sales-channel-api/v{version}/customer/address/{id}/default-billing", name="sales-channel-api.customer.address.set-default-billing-address", methods={"PATCH"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     */
    public function setDefaultBillingAddress(string $id, SalesChannelContext $context): JsonResponse
    {
        $this->accountService->setDefaultBillingAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    private function loadOrders(int $page, int $limit, SalesChannelContext $context): OrderCollection
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        --$page;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        $criteria->addSorting(new FieldSorting('order.orderDateTime', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        /** @var OrderCollection $orders */
        $orders = $this->orderRepository->search($criteria, $context->getContext())->getEntities();

        return $orders;
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }
}
