<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class DeleteCustomerRoute extends AbstractDeleteCustomerRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractDeleteCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Delete(
     *      path="/account/customer",
     *      description="Delete customer profile",
     *      operationId="deleteCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Loggedin customer",
     *          @OA\JsonContent(ref="#/components/schemas/customer_flat")
     *     )
     * )
     * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer.delete", methods={"DELETE"})
     */
    public function delete(SalesChannelContext $context): NoContentResponse
    {
        if (!Feature::isActive('FEATURE_NEXT_10077')) {
            return new NoContentResponse();
        }

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $this->customerRepository->delete([['id' => $customer->getId()]], $context->getContext());

        $event = new CustomerDeletedEvent($context, $customer);
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }
}
