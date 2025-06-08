<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class CustomerGroupRegistrationActionController
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     *
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerGroupRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesChannelContextRestorer $restorer
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/customer-group-registration/accept', name: 'api.customer-group.accept', methods: ['POST'], requirements: ['version' => '\d+'])]
    public function accept(Request $request, Context $context): JsonResponse
    {
        $customerIds = $this->getRequestCustomerIds($request);

        $silentError = $request->request->getBoolean('silentError');

        $customers = $this->fetchCustomers($customerIds, $context, $silentError);

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
            $salesChannelContext = $this->restorer->restoreByCustomer($customer->getId(), $context);

            /** @var CustomerEntity $customer */
            $customer = $salesChannelContext->getCustomer();
            $customerGroupId = $customer->getGroupId();

            $criteria = (new Criteria([$customerGroupId]))
                ->setLimit(1);

            $customerRequestedGroup = $this->customerGroupRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();
            if (!$customerRequestedGroup) {
                throw CustomerException::customerGroupNotFound($customerGroupId);
            }

            $this->eventDispatcher->dispatch(new CustomerGroupRegistrationAccepted(
                $customer,
                $customerRequestedGroup,
                $salesChannelContext->getContext()
            ));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/customer-group-registration/decline', name: 'api.customer-group.decline', methods: ['POST'], requirements: ['version' => '\d+'])]
    public function decline(Request $request, Context $context): JsonResponse
    {
        $customerIds = $this->getRequestCustomerIds($request);

        $silentError = $request->request->getBoolean('silentError');

        $customers = $this->fetchCustomers($customerIds, $context, $silentError);

        $updateData = [];

        foreach ($customers as $customer) {
            $updateData[] = [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
            ];
        }

        $this->customerRepository->update($updateData, $context);

        foreach ($customers as $customer) {
            $customerId = $customer->getId();
            $salesChannelContext = $this->restorer->restoreByCustomer($customerId, $context);

            $customer = $salesChannelContext->getCustomer();
            if (!$customer) {
                throw CustomerException::customersNotFound([$customerId]);
            }
            $customerGroupId = $customer->getGroupId();

            $criteria = (new Criteria([$customerGroupId]))
                ->setLimit(1);

            $customerRequestedGroup = $this->customerGroupRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();
            if (!$customerRequestedGroup) {
                throw CustomerException::customerGroupNotFound($customerGroupId);
            }

            $this->eventDispatcher->dispatch(new CustomerGroupRegistrationDeclined(
                $customer,
                $customerRequestedGroup,
                $salesChannelContext->getContext()
            ));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string>
     */
    private function getRequestCustomerIds(Request $request): array
    {
        $customerIds = $request->request->all('customerIds');

        if (!empty($customerIds)) {
            $customerIds = array_unique($customerIds);
        }

        if (empty($customerIds)) {
            throw CustomerException::customerIdsParameterIsMissing();
        }

        return $customerIds;
    }

    /**
     * @param array<string> $customerIds
     *
     * @return array<CustomerEntity>
     */
    private function fetchCustomers(array $customerIds, Context $context, bool $silentError = false): array
    {
        $criteria = new Criteria($customerIds);
        $result = $this->customerRepository->search($criteria, $context);
        if ($result->getTotal() === 0) {
            throw CustomerException::customersNotFound($customerIds);
        }

        $customers = [];

        foreach ($result->getEntities() as $customer) {
            if (!$customer->getRequestedGroupId()) {
                if ($silentError === false) {
                    throw CustomerException::groupRequestNotFound($customer->getId());
                }

                continue;
            }

            $customers[] = $customer;
        }

        return $customers;
    }
}
