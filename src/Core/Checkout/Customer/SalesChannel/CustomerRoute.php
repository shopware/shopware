<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @Entity("customer")
     * @OA\Get(
     *      path="/account/customer",
     *      description="Returns informations about the loggedin customer",
     *      operationId="readCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Loggedin customer",
     *          @OA\JsonContent(ref="#/components/schemas/customer_flat")
     *     )
     * )
     * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): CustomerResponse
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->customerDefinition, $context->getContext());
        }
        $criteria->setIds([$context->getCustomer()->getId()]);

        $customer = $this->customerRepository->search($criteria, $context->getContext())->first();

        return new CustomerResponse($customer);
    }
}
