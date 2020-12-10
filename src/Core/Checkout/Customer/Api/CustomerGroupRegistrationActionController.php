<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Api;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"api"})
 */
class CustomerGroupRegistrationActionController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $customerRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Since("6.3.1.0")
     * @Route("/api/_action/customer-group-registration/accept/{customerId}", name="api.customer-group.accept", methods={"POST"}, requirements={"version"="\d+"})
     */
    public function accept(string $customerId, Context $context): JsonResponse
    {
        $customer = $this->fetchCustomer($customerId, $context);

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
                'groupId' => $customer->getRequestedGroupId(),
            ],
        ], $context);

        $this->eventDispatcher->dispatch(new CustomerGroupRegistrationAccepted(
            $customer,
            $customer->getRequestedGroup(),
            $context
        ));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.3.1.0")
     * @Route("/api/_action/customer-group-registration/decline/{customerId}", name="api.customer-group.decline", methods={"POST"}, requirements={"version"="\d+"})
     */
    public function decline(string $customerId, Context $context): JsonResponse
    {
        $customer = $this->fetchCustomer($customerId, $context);

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
            ],
        ], $context);

        $this->eventDispatcher->dispatch(new CustomerGroupRegistrationDeclined(
            $customer,
            $customer->getRequestedGroup(),
            $context
        ));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function fetchCustomer(string $customerId, Context $context): CustomerEntity
    {
        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('salutation');

        $result = $this->customerRepository->search($criteria, $context);

        if ($result->getTotal()) {
            /** @var CustomerEntity $customer */
            $customer = $result->first();

            if ($customer->getRequestedGroupId() === null) {
                throw new \RuntimeException('User dont have approval');
            }

            return $customer;
        }

        throw new \RuntimeException('Cannot find Customer');
    }
}
