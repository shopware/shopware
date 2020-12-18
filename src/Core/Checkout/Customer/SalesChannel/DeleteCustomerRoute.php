<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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
     * @Since("6.3.2.0")
     * @OA\Delete(
     *      path="/account/customer",
     *      summary="Delete customer profile",
     *      operationId="deleteCustomer",
     *      tags={"Store API", "Account"},
     *      @OA\Response(
     *          response="204",
     *          description="Successfully deleted the customer",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @LoginRequired()
     * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer.delete", methods={"DELETE"})
     */
    public function delete(SalesChannelContext $context, ?CustomerEntity $customer = null): NoContentResponse
    {
        /* @deprecated tag:v6.4.0 - Parameter $customer will be mandatory when using with @LoginRequired() */
        if (!$customer) {
            /** @var CustomerEntity $customer */
            $customer = $context->getCustomer();
        }

        $this->customerRepository->delete([['id' => $customer->getId()]], $context->getContext());

        $event = new CustomerDeletedEvent($context, $customer);
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }
}
