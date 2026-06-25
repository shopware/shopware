<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CustomerGroupRegistrationActionController::class)]
#[Package('checkout')]
class CustomerGroupRegistrationActionControllerTest extends TestCase
{
    private CustomerGroupRegistrationActionController $controllerMock;

    /**
     * @var MockObject&EntityRepository<CustomerCollection>
     */
    private MockObject&EntityRepository $customerRepositoryMock;

    /**
     * @var MockObject&EntityRepository<CustomerGroupCollection>
     */
    private MockObject&EntityRepository $customerGroupRepositoryMock;

    private MockObject&EventDispatcher $eventDispatcherMock;

    private MockObject&SalesChannelContextRestorer $restorerMock;

    protected function setUp(): void
    {
        $this->customerRepositoryMock = $this->createMock(EntityRepository::class);
        $this->customerGroupRepositoryMock = $this->createMock(EntityRepository::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->restorerMock = $this->createMock(SalesChannelContextRestorer::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $this->restorerMock->method('restoreByCustomer')->willReturn($salesChannelContext);

        $this->controllerMock = new CustomerGroupRegistrationActionController(
            $this->customerRepositoryMock,
            $this->customerGroupRepositoryMock,
            $this->eventDispatcherMock,
            $this->restorerMock,
        );
    }

    /**
     * @param CustomerEntity[] $customers
     */
    #[DataProvider('groupRegistrationActionDataProvider')]
    public function testGroupRegistrationAcceptMatches(?int $expectedResCode, ?array $customers, Request $request, ?\Exception $expectedException): void
    {
        $context = Context::createDefaultContext();

        if ($customers !== null) {
            $customerCollection = new CustomerCollection($customers);
            $this->setSearchReturn($context, $customerCollection);
            $this->setCustomerGroupSearchReturn($context, $customerCollection);
        }

        if ($expectedException !== null && $expectedResCode === null) {
            $this->expectExceptionObject($expectedException);
        }

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @param CustomerEntity[] $customers
     */
    #[DataProvider('groupRegistrationActionDataProvider')]
    public function testGroupRegistrationDeclineMatches(?int $expectedResCode, ?array $customers, Request $request, ?\Exception $expectedException): void
    {
        $context = Context::createDefaultContext();

        if ($customers !== null) {
            $customerCollection = new CustomerCollection($customers);
            $this->setSearchReturn($context, $customerCollection);
            $this->setCustomerGroupSearchReturn($context, $customerCollection);
        }

        if ($expectedException !== null && $expectedResCode === null) {
            $this->expectExceptionObject($expectedException);
        }

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @return iterable<string, array{int|null, array<CustomerEntity>|null, Request, \Exception|null}>
     */
    public static function groupRegistrationActionDataProvider(): iterable
    {
        $invalidCustomer = Uuid::randomHex();
        yield 'without user' => [null, null, self::createRequest([$invalidCustomer]), CustomerException::customersNotFound([$invalidCustomer])];

        $missingCustomer = self::createCustomer();
        $missingCustomerId = $missingCustomer->getId();
        yield 'without customer' => [null, null, self::createRequest([$missingCustomerId]), CustomerException::customersNotFound([$missingCustomerId])];

        yield 'without customerId' => [null, null, self::createRequest([]), CustomerException::customerIdsParameterIsMissing()];

        $customerWithoutRequest = self::createCustomer(false);
        $customerWithoutRequestId = $customerWithoutRequest->getId();
        yield 'without request group' => [null, [$customerWithoutRequest], self::createRequest([$customerWithoutRequestId]), CustomerException::groupRequestNotFound($customerWithoutRequestId)];

        $acceptCustomer = self::createCustomer();
        $acceptCustomerId = $acceptCustomer->getId();
        yield 'accept/decline' => [204, [$acceptCustomer], self::createRequest([$acceptCustomerId]), null];

        $silentCustomer = self::createCustomer(false);
        $silentCustomerId = $silentCustomer->getId();
        yield 'accept/decline silent' => [204, [$silentCustomer], self::createRequest([$silentCustomerId], true), null];

        $batchCustomerA = self::createCustomer();
        $batchCustomerAId = $batchCustomerA->getId();
        $batchCustomerB = self::createCustomer();
        $batchCustomerBId = $batchCustomerB->getId();
        yield 'in batch' => [204, [$batchCustomerA, $batchCustomerB], self::createRequest([$batchCustomerAId, $batchCustomerBId]), null];
    }

    public function testDeclineCustomerRequestedGroupIsSetCorrectly(): void
    {
        $context = Context::createDefaultContext();

        $assignedCustomerGroup = new CustomerGroupEntity();
        $assignedCustomerGroup->setId(Uuid::randomHex());

        $requestedCustomerGroup = new CustomerGroupEntity();
        $requestedCustomerGroup->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $customer->setRequestedGroupId($requestedCustomerGroup->getId());
        $customer->setRequestedGroup($requestedCustomerGroup);
        $customer->setGroupId($assignedCustomerGroup->getId());

        $request = self::createRequest([$customer->getId()]);

        $this->setSearchReturn($context, new CustomerCollection([$customer]));

        $this->customerGroupRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                CustomerGroupDefinition::ENTITY_NAME,
                1,
                new CustomerGroupCollection([$requestedCustomerGroup]),
                null,
                new Criteria(),
                $context,
            )
        );

        // test case to ensure the event contains the declined requested customer group
        $this->eventDispatcherMock->method('dispatch')->willReturnCallback(static function (CustomerGroupRegistrationDeclined $customerGroupRegistrationDeclined) use ($customer, $requestedCustomerGroup) {
            static::assertSame($customer, $customerGroupRegistrationDeclined->getCustomer());
            static::assertSame($requestedCustomerGroup, $customerGroupRegistrationDeclined->getCustomerGroup());

            return $customerGroupRegistrationDeclined;
        });

        $this->controllerMock->decline($request, $context);
    }

    private static function createCustomer(bool $requestedGroup = true): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        if ($requestedGroup) {
            $customerGroup = new CustomerGroupEntity();
            $customerGroup->setId(Uuid::randomHex());
            $customer->setRequestedGroup($customerGroup);
            $customer->setRequestedGroupId($customerGroup->getId());
        }

        return $customer;
    }

    /**
     * @param string[] $customerId
     */
    private static function createRequest(array $customerId, bool $silentError = false): Request
    {
        $request = new Request();
        $request->request->add(['customerIds' => $customerId, 'silentError' => $silentError]);

        return $request;
    }

    private function setSearchReturn(Context $context, ?CustomerCollection $collection = null): void
    {
        if (!$collection instanceof CustomerCollection) {
            $collection = new CustomerCollection();
        }
        $criteria = new Criteria(array_values($collection->getIds()));

        $this->customerRepositoryMock->method('search')->with(
            $criteria,
            $context,
        )
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    CustomerDefinition::ENTITY_NAME,
                    $collection->count(),
                    $collection,
                    null,
                    $criteria,
                    $context
                ),
            );
    }

    private function setCustomerGroupSearchReturn(Context $context, CustomerCollection $customers): void
    {
        $customerGroups = [];
        foreach ($customers as $customer) {
            $requestedGroupId = $customer->getRequestedGroupId();
            if ($requestedGroupId === null || isset($customerGroups[$requestedGroupId])) {
                continue;
            }

            $customerGroup = new CustomerGroupEntity();
            $customerGroup->setId($requestedGroupId);
            $customerGroups[$requestedGroupId] = $customerGroup;
        }

        $collection = new CustomerGroupCollection(\array_values($customerGroups));

        $this->customerGroupRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                CustomerGroupDefinition::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                $context,
            )
        );
    }
}
