<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CustomerRoute extends AbstractCustomerRoute
{
    /**
     * @var CustomerDefinition
     */
    private $customerDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    public function __construct(
        CustomerDefinition $customerDefinition,
        EntityRepositoryInterface $customerRepository,
        RequestCriteriaBuilder $requestCriteriaBuilder
    ) {
        $this->customerDefinition = $customerDefinition;
        $this->customerRepository = $customerRepository;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
    }

    public function getDecorated(): AbstractCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("customer")
     * @OA\Post(
     *      path="/account/customer",
     *      summary="Returns informations about the loggedin customer",
     *      operationId="readCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Loggedin customer",
     *          @OA\JsonContent(ref="#/components/schemas/customer_flat")
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null, ?CustomerEntity $customer = null): CustomerResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->customerDefinition, $context->getContext());
        }

        /* @deprecated tag:v6.4.0 - Parameter $customer will be mandatory when using with @LoginRequired() */
        if (!$customer) {
            $customer = $context->getCustomer();
        }

        $criteria->setIds([$customer->getId()]);

        $customer = $this->customerRepository->search($criteria, $context->getContext())->first();

        return new CustomerResponse($customer);
    }
}
