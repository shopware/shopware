<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        EntityRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
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
     *      summary="Get information about current customer",
     *      description="Returns information about the current customer.",
     *      operationId="readCustomer",
     *      tags={"Store API", "Profile"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Returns the logged in customer, also for guest sessions. Check for the value of `guest` field to see whether the customer is a guest.",
     *          @OA\JsonContent(ref="#/components/schemas/Customer")
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route("/store-api/account/customer", name="store-api.account.customer", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): CustomerResponse
    {
        $criteria->setIds([$customer->getId()]);

        $customerEntity = $this->customerRepository->search($criteria, $context->getContext())->first();

        return new CustomerResponse($customerEntity);
    }
}
