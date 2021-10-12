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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"api"})
 */
class CustomerGroupRegistrationActionController
{
    private EntityRepositoryInterface $customerRepository;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EntityRepositoryInterface $customerRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_17261) tag:v6.5.0 - customerId route parameter will be no longer required, use customerIds in body instead
     *
     * @Since("6.3.1.0")
     * @Route("/api/_action/customer-group-registration/accept/{customerId}", name="api.customer-group.accept", methods={"POST"}, requirements={"version"="\d+"}, defaults={"customerId"=null})
     */
    public function accept(Request $request, Context $context): JsonResponse
    {
        $customerIds = $this->getRequestCustomerIds($request);

        $silentError = $request->request->getBoolean('silentError', false);

        $customers = $this->fetchCustomers($customerIds, $context, $silentError);

        if (empty($customers)) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        $updateData = [];

        foreach ($customers as $customer) {
            $updateData[] = [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
                'groupId' => $customer->getRequestedGroupId(),
            ];
        }

        $this->customerRepository->update($updateData, $context);

        foreach ($customers as $customer) {
            $this->eventDispatcher->dispatch(new CustomerGroupRegistrationAccepted(
                $customer,
                $customer->getRequestedGroup(),
                $context
            ));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_17261) tag:v6.5.0 - customerId route parameter will be no longer required, use customerIds in body instead
     *
     * @Since("6.3.1.0")
     * @Route("/api/_action/customer-group-registration/decline/{customerId}", name="api.customer-group.decline", methods={"POST"}, requirements={"version"="\d+"}, defaults={"customerId"=null})
     */
    public function decline(Request $request, Context $context): JsonResponse
    {
        $customerIds = $this->getRequestCustomerIds($request);

        $silentError = $request->request->getBoolean('silentError', false);

        $customers = $this->fetchCustomers($customerIds, $context, $silentError);

        if (empty($customers)) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        $updateData = [];

        foreach ($customers as $customer) {
            $updateData[] = [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
            ];
        }

        $this->customerRepository->update($updateData, $context);

        foreach ($customers as $customer) {
            $this->eventDispatcher->dispatch(new CustomerGroupRegistrationDeclined(
                $customer,
                $customer->getRequestedGroup(),
                $context
            ));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_17261) tag:v6.5.0 - customerId route parameter will be removed so just get customerIds from request body
     */
    private function getRequestCustomerIds(Request $request): array
    {
        $customerIds = [];

        $customerId = $request->attributes->get('customerId');

        if ($customerId !== null) {
            $customerIds[] = $customerId;
        }

        $requestCustomerIds = $request->request->get('customerIds', \json_encode([]));

        if (\is_string($requestCustomerIds)) {
            $requestCustomerIds = \json_decode($requestCustomerIds, true);
        }

        if (!empty($requestCustomerIds)) {
            $customerIds = array_unique(array_merge($customerIds, $requestCustomerIds));
        }

        if (empty($customerIds)) {
            throw new \InvalidArgumentException('customerId or customerIds parameter are missing');
        }

        return $customerIds;
    }

    private function fetchCustomers(array $customerIds, Context $context, bool $silentError = false): array
    {
        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('salutation');

        $result = $this->customerRepository->search($criteria, $context);

        $customers = [];

        if ($result->getTotal()) {
            /** @var CustomerEntity $customer */
            foreach ($result->getElements() as $customer) {
                if ($customer->getRequestedGroupId() === null) {
                    if ($silentError === false) {
                        throw new \RuntimeException(sprintf('User %s dont have approval', $customer->getId()));
                    }

                    continue;
                }

                $customers[] = $customer;
            }

            return $customers;
        }

        throw new \RuntimeException('Cannot find Customers');
    }
}
