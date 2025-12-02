<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Exception;
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

    /** @var MockObject&EntityRepository<CustomerCollection> */
    private MockObject&EntityRepository $customerRepositoryMock;

    /** @var MockObject&EntityRepository<CustomerGroupCollection> */
    private MockObject&EntityRepository $customerGroupRepositoryMock;

    private MockObject&SalesChannelContextRestorer $contextRestorerMock;

    private MockObject&EventDispatcher $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->customerRepositoryMock = $this->createMock(EntityRepository::class);
        $this->customerGroupRepositoryMock = $this->createMock(EntityRepository::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->contextRestorerMock = $this->createMock(SalesChannelContextRestorer::class);

        $this->controllerMock = new CustomerGroupRegistrationActionController(
            $this->customerRepositoryMock,
            $this->customerGroupRepositoryMock,
            $this->eventDispatcherMock,
            $this->contextRestorerMock,
        );
    }

    /**
     * @param CustomerEntity[] $customers
     *
     * @throws Exception
     */
    #[DataProvider('getRegistrationValues')]
    public function testGroupRegistrationAcceptMatches(?int $expectedResCode, ?array $customers, Request $request, ?string $errorMessage): void
    {
        $context = Context::createDefaultContext();

        if ($customers !== null) {
            $customerCollection = new CustomerCollection($customers);
            $this->setRestorerReturn();
            $this->setSearchReturn($context, $customerCollection);
            $this->setCustomerGroupSearchReturn($context);
        }

        if ($errorMessage !== null && $expectedResCode === null) {
            static::expectExceptionMessage($errorMessage);
        }

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @param CustomerEntity[] $customers
     *
     * @throws Exception
     */
    #[DataProvider('getRegistrationValues')]
    public function testGroupRegistrationDeclineMatches(?int $expectedResCode, ?array $customers, Request $request, ?string $errorMessage): void
    {
        $context = Context::createDefaultContext();

        if ($customers !== null) {
            $customerCollection = new CustomerCollection($customers);
            $this->setRestorerReturn();
            $this->setSearchReturn($context, $customerCollection);
            $this->setCustomerGroupSearchReturn($context);
        }

        if ($errorMessage !== null && $expectedResCode === null) {
            static::expectExceptionMessage($errorMessage);
        }

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @return array<string, array{int|null, array<CustomerEntity>|null, Request, string|null}>
     */
    public static function getRegistrationValues(): array
    {
        $customer = self::createCustomer();
        $customerB = self::createCustomer();
        $invalidCustomer = Uuid::randomHex();
        $customerWithoutRequest = self::createCustomer(false);

        return [
            'without user' => [null, null, self::createRequest([$invalidCustomer]), \sprintf('These customers "%s" are not found', $invalidCustomer)],
            'without customer' => [null, null, self::createRequest([$customer->getId()]),  \sprintf('These customers "%s" are not found', $customer->getId())],
            'without customerId' => [null, null, self::createRequest([]), 'Parameter "customerIds" is missing.'],
            'without request group' => [null,  [$customerWithoutRequest], self::createRequest([$customerWithoutRequest->getId()]), \sprintf('Group request for customer "%s" is not found', $customerWithoutRequest->getId())],
            'accept/decline' => [204, [$customer], self::createRequest([$customer->getId()]),  null],
            'accept/decline silent' => [204,  [$customerWithoutRequest], self::createRequest([$customerWithoutRequest->getId()], true), null],
            'in batch' => [204, [$customer, $customerB], self::createRequest([$customer->getId(), $customerB->getId()]), null],
        ];
    }

    public function testDeclineThrowsExceptionOnNoRequestedCustomerGroupId(): void
    {
        $context = Context::createDefaultContext();
        $customer = self::createCustomer();
        $request = self::createRequest([$customer->getId()]);

        $this->setSearchReturn($context, new CustomerCollection([$customer]));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->contextRestorerMock->method('restoreByCustomer')->willReturnCallback(function (string $customerId) use ($salesChannelContext) {
            /** @phpstan-ignore shopware.mockingSimpleObjects (A mock is used here to ensure that the method getRequestedGroupId is called and returns null) */
            $returnCustomer = $this->createMock(CustomerEntity::class);
            $returnCustomer->method('getId')->willReturn($customerId);
            $returnCustomer->expects($this->once())->method('getRequestedGroupId')->willReturn(null);

            $salesChannelContext->expects(static::once())->method('getCustomer')->willReturn($returnCustomer);

            return $salesChannelContext;
        });

        $this->expectExceptionObject(CustomerException::groupRequestNotFound($customer->getId()));

        $this->controllerMock->decline($request, $context);
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
        $customer->setRequestedGroupId($requestedCustomerGroup->getId());
        $customer->setRequestedGroup($requestedCustomerGroup);
        $customer->setGroupId($assignedCustomerGroup->getId());

        $request = self::createRequest([$customer->getId()]);

        $this->setSearchReturn($context, new CustomerCollection([$customer]));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->contextRestorerMock->method('restoreByCustomer')->willReturnCallback(function (string $customerId, Context $context) use ($customer, $salesChannelContext) {
            $salesChannelContext->method('getCustomer')->willReturn($customer);
            $salesChannelContext->method('getContext')->willReturn($context);

            return $salesChannelContext;
        });

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
        $this->eventDispatcherMock->method('dispatch')->willReturnCallback(function (CustomerGroupRegistrationDeclined $customerGroupRegistrationDeclined) use ($customer, $requestedCustomerGroup) {
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

    private function setCustomerGroupSearchReturn(Context $context): void
    {
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId(Uuid::class);
        $collection = new CustomerGroupCollection([$customerGroup]);

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

    private function setRestorerReturn(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->contextRestorerMock->method('restoreByCustomer')->willReturnCallback(function (string $customerId, Context $context) use ($salesChannelContext) {
            $customer = new CustomerEntity();
            $customer->setGroupId(Uuid::randomHex());

            $customer->setRequestedGroup(new CustomerGroupEntity());
            $customer->setRequestedGroupId(Uuid::randomHex());
            $customer->setId($customerId);

            $salesChannelContext->method('getCustomer')->willReturn($customer);
            $salesChannelContext->method('getContext')->willReturn($context);

            return $salesChannelContext;
        });
    }
}
