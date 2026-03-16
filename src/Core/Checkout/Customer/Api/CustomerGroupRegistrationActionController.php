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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
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
        private readonly SalesChannelContextRestorer $restorer,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/_action/customer-group-registration/accept', name: 'api.customer-group.accept', methods: ['POST'], requirements: ['version' => '\d+'])]
    public function accept(Request $request, Context $context): JsonResponse
    {
        $silentError = $request->request->getBoolean('silentError');
        $customerIds = $this->getRequestCustomerIds($request);
        $customers = $this->fetchCustomers($customerIds, $context, $silentError);
        $requestedCustomerGroups = $this->fetchRequestedCustomerGroups($customers, $context);

        $updateData = [];
        foreach ($customers as $customer) {
            $customerGroupId = $customer->getRequestedGroupId();
            \assert($customerGroupId !== null);

            $customerRequestedGroup = $requestedCustomerGroups->get($customerGroupId);
            if ($customerRequestedGroup === null) {
                throw CustomerException::customerGroupNotFound($customerGroupId);
            }

            $updateData[] = [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
                'groupId' => $customerGroupId,
            ];
        }

        $this->customerRepository->update($updateData, $context);

        foreach ($customers as $customer) {
            $customerGroupId = $customer->getRequestedGroupId();
            \assert($customerGroupId !== null);

            $customerRequestedGroup = $requestedCustomerGroups->get($customerGroupId);
            if ($customerRequestedGroup === null) {
                throw CustomerException::customerGroupNotFound($customerGroupId);
            }

            $customer->setGroupId($customerGroupId);
            $customer->setRequestedGroupId(null);

            $customerContext = $this->createCustomerEventContext($context, $customer);

            $this->eventDispatcher->dispatch(new CustomerGroupRegistrationAccepted(
                $customer,
                $customerRequestedGroup,
                $customerContext
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
        $silentError = $request->request->getBoolean('silentError');

        $customerIds = $this->getRequestCustomerIds($request);
        $customers = $this->fetchCustomers($customerIds, $context, $silentError);
        $requestedCustomerGroups = $this->fetchRequestedCustomerGroups($customers, $context);

        $updateData = [];
        foreach ($customers as $customer) {
            $requestedCustomerGroupId = $customer->getRequestedGroupId();
            \assert($requestedCustomerGroupId !== null);

            $requestedCustomerGroup = $requestedCustomerGroups->get($requestedCustomerGroupId);
            if ($requestedCustomerGroup === null) {
                throw CustomerException::customerGroupNotFound($requestedCustomerGroupId);
            }

            $customerContext = $this->createCustomerEventContext($context, $customer);

            $this->eventDispatcher->dispatch(new CustomerGroupRegistrationDeclined(
                $customer,
                $requestedCustomerGroup,
                $customerContext
            ));

            $updateData[] = [
                'id' => $customer->getId(),
                'requestedGroupId' => null,
            ];
        }

        $this->customerRepository->update($updateData, $context);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return non-empty-array<string>
     */
    private function getRequestCustomerIds(Request $request): array
    {
        $customerIds = $request->request->all('customerIds');

        if ($customerIds !== []) {
            $customerIds = array_unique($customerIds);
        }

        if ($customerIds === []) {
            throw CustomerException::customerIdsParameterIsMissing();
        }

        return $customerIds;
    }

    /**
     * @param non-empty-array<string> $customerIds
     */
    private function fetchCustomers(array $customerIds, Context $context, bool $silentError = false): CustomerCollection
    {
        $criteria = new Criteria($customerIds);
        $result = $this->customerRepository->search($criteria, $context);
        if ($result->getTotal() === 0) {
            throw CustomerException::customersNotFound($customerIds);
        }

        $customers = new CustomerCollection();

        foreach ($result->getEntities() as $customer) {
            if (!$customer->getRequestedGroupId()) {
                if ($silentError === false) {
                    throw CustomerException::groupRequestNotFound($customer->getId());
                }

                continue;
            }

            $customers->add($customer);
        }

        return $customers;
    }

    private function fetchRequestedCustomerGroups(CustomerCollection $customers, Context $context): CustomerGroupCollection
    {
        if ($customers->count() === 0) {
            return new CustomerGroupCollection();
        }

        $requestedCustomerGroupIds = [];
        foreach ($customers as $customer) {
            $requestedCustomerGroupId = $customer->getRequestedGroupId();

            if (!\is_string($requestedCustomerGroupId)) {
                continue;
            }

            $requestedCustomerGroupIds[] = $requestedCustomerGroupId;
        }

        $criteria = new Criteria(\array_values(\array_unique($requestedCustomerGroupIds)));

        return $this->customerGroupRepository->search($criteria, $context)->getEntities();
    }

    private function createCustomerEventContext(Context $context, CustomerEntity $customer): Context
    {
        if (!Feature::isActive('v6.8.0.0') && $customer->getActive()) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf(
                    'Using a SalesChannelContext for customer-group registration events is deprecated and will be removed in 6.8.0.0. Use the event Context and the customer/customer-group payload of the event instead (%s).',
                    self::class
                )
            );

            return $this->restorer->restoreByCustomer($customer->getId(), $context)->getContext();
        }

        $customerLanguageChain = \array_values(\array_unique(\array_filter([$customer->getLanguageId(), ...$context->getLanguageIdChain()])));

        $customerContext = clone $context;
        $customerContext->assign(['languageIdChain' => $customerLanguageChain]);

        return $customerContext;
    }
}
