<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Customer\Util\CustomerContextPersister;
use Shopware\Core\Checkout\Customer\Util\CustomerContextService;
use Shopware\Core\Checkout\Order\Exception\NotLoggedInCustomerException;
use Shopware\Core\Checkout\Order\OrderRepository;
use Shopware\Core\Framework\Api\Context\RestContext;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Firewall\CustomerProvider;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Page\Account\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Serializer\Serializer;

class StorefrontCustomerController extends Controller
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var CustomerContextPersister
     */
    private $contextPersister;

    /**
     * @var CustomerProvider
     */
    private $customerProvider;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var CustomerContextService
     */
    private $storefrontContextService;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    public function __construct(
        Serializer $serializer,
        CustomerContextPersister $contextPersister,
        CustomerProvider $customerProvider,
        AccountService $accountService,
        CustomerContextService $storefrontContextService,
        ResponseFactory $responseFactory,
        OrderRepository $orderRepository
    ) {
        $this->serializer = $serializer;
        $this->contextPersister = $contextPersister;
        $this->customerProvider = $customerProvider;
        $this->accountService = $accountService;
        $this->storefrontContextService = $storefrontContextService;
        $this->responseFactory = $responseFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/storefront-api/customer/login", name="storefront.api.customer.login")
     * @Method({"POST"})
     */
    public function login(Request $request, CustomerContext $context): JsonResponse
    {
        $post = $this->decodedContent($request);

        if (empty($post['username']) || empty($post['password'])) {
            throw new BadCredentialsException();
        }

        $username = $post['username'];

        $user = $this->customerProvider->loadUserByUsername($username);

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getTenantId()
        );

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken(),
        ]);
    }

    /**
     * @Route("/storefront-api/customer/logout", name="storefront.api.customer.logout")
     * @Method({"POST"})
     */
    public function logout(CustomerContext $context): JsonResponse
    {
        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getTenantId()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer/default-billing-address/{id}", name="storefront.api.customer.default_billing_address.update")
     * @Method({"PUT"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function setDefaultBillingAddress(string $id, CustomerContext $context)
    {
        $this->accountService->setDefaultBillingAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    /**
     * @Route("/storefront-api/customer/orders", name="storefront.api.customer.orders.get")
     * @Method({"GET"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function orderOverview(Request $request, CustomerContext $context): JsonResponse
    {
        $content = $this->decodedContent($request);

        $limit = 10;
        $page = 1;

        if (array_key_exists('limit', $content)) {
            $limit = (int) $content['limit'];
        }
        if (array_key_exists('page', $content)) {
            $limit = (int) $content['page'];
        }

        return new JsonResponse($this->serialize($this->loadOrders($page, $limit, $context)));
    }

    /**
     * @Route("/storefront-api/customer", name="storefront.api.customer.create")
     * @Method({"POST"})
     */
    public function register(Request $request, CustomerContext $context): JsonResponse
    {
        $content = $this->decodedContent($request);
        $customerId = $this->accountService->createNewCustomer($content, $context);

        return new JsonResponse($this->serialize($customerId));
    }

    /**
     * @Route("/storefront-api/customer/email", name="storefront.api.customer.email.update")
     * @Method({"PUT"})
     */
    public function changeEmail(Request $request, CustomerContext $context): JsonResponse
    {
        $content = $this->decodedContent($request);

        $this->accountService->changeEmail($content['email'], $context);
        $this->storefrontContextService->refresh(
            $context->getTenantId(),
            $context->getTouchpoint()->getId(),
            $context->getToken()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer/password", name="storefront.api.customer.password.update")
     * @Method({"PUT"})
     */
    public function changePassword(Request $request, CustomerContext $context): JsonResponse
    {
        $content = $this->decodedContent($request);
        if (!array_key_exists('password', $content) || empty($content['password'])) {
            return new JsonResponse($this->serialize('Invalid password'));
        }
        $this->accountService->changePassword($content['password'], $context);
        $this->storefrontContextService->refresh(
            $context->getTenantId(),
            $context->getTouchpoint()->getId(),
            $context->getToken()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer/profile", name="storefront.api.customer.profile.update")
     * @Method({"PUT"})
     */
    public function changeProfile(Request $request, CustomerContext $context): JsonResponse
    {
        $profile = $this->decodedContent($request);
        $this->accountService->changeProfile($profile, $context);
        $this->storefrontContextService->refresh(
            $context->getTenantId(),
            $context->getTouchpoint()->getId(),
            $context->getToken()
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/storefront-api/customer", name="storefront.api.customer.detail.get")
     * @Method({"GET"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function getCustomerDetail(Request $request, CustomerContext $context): Response
    {
        return $this->responseFactory->createDetailResponse(
            $this->accountService->getCustomerByContext($context),
            CustomerDefinition::class,
            new RestContext($request, $context->getContext(), null)
        );
    }

    /**
     * @Route("/storefront-api/customer/addresses", name="storefront.api.customer.addresses.get")
     * @Method({"GET"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function getAddresses(CustomerContext $context): JsonResponse
    {
        return new JsonResponse(
            $this->serialize($this->accountService->getAddressesByCustomer($context))
        );
    }

    /**
     * @Route("/storefront-api/customer/address/{id}", name="storefront.api.customer.address.get")
     * @Method({"GET"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function getAddress(string $id, CustomerContext $context): JsonResponse
    {
        return new JsonResponse(
            $this->serialize($this->accountService->getAddressById($id, $context))
        );
    }

    /**
     * @Route("/storefront-api/customer/address", name="storefront.api.customer.address.create")
     * @Method({"POST"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function createAddress(Request $request, CustomerContext $context): JsonResponse
    {
        $content = $this->decodedContent($request);
        $addressId = $this->accountService->saveAddress($content, $context);

        $this->storefrontContextService->refresh($context->getTenantId(), $context->getTouchpoint()->getId(), $context->getToken());

        return new JsonResponse($this->serialize($addressId));
    }

    /**
     * @Route("/storefront-api/customer/address/{id}", name="storefront.api.customer.address.delete")
     * @Method({"DELETE"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function deleteAddress(string $id, CustomerContext $context): JsonResponse
    {
        $this->accountService->deleteAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    /**
     * @Route("/storefront-api/customer/default-shipping-address/{id}", name="storefront.api.customer.default_shipping_address.update")
     * @Method({"PUT"})
     *
     * @throws NotLoggedInCustomerException
     */
    public function setDefaultShippingAddress(string $id, CustomerContext $context)
    {
        $this->accountService->setDefaultShippingAddress($id, $context);

        return new JsonResponse($this->serialize($id));
    }

    private function loadOrders(int $page, int $limit, CustomerContext $context): array
    {
        $page = $page - 1;

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.customerId', $context));
        $criteria->addSorting(new FieldSorting('order.date', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        return $this->orderRepository->search($criteria, $context->getContext())->getElements();
    }

    private function decodedContent(Request $request): array
    {
        if (empty($request->getContent())) {
            return [];
        }

        return $this->serializer->decode($request->getContent(), 'json');
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }
}
