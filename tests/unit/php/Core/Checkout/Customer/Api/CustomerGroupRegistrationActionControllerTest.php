<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package customer-order
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController
 */
class CustomerGroupRegistrationActionControllerTest extends TestCase
{
    private CustomerGroupRegistrationActionController $controllerMock;

    private MockObject&EntityRepository $customerRepositoryMock;

    private MockObject&EntityRepository $customerGroupRepositoryMock;

    private MockObject&SalesChannelContextRestorer $contextRestorerMock;

    public function setUp(): void
    {
        $this->customerRepositoryMock = $this->createMock(EntityRepository::class);
        $this->customerGroupRepositoryMock = $this->createMock(EntityRepository::class);
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->contextRestorerMock = $this->createMock(SalesChannelContextRestorer::class);

        $this->controllerMock = new CustomerGroupRegistrationActionController(
            $this->customerRepositoryMock,
            $this->customerGroupRepositoryMock,
            $eventDispatcherMock,
            $this->contextRestorerMock,
        );
    }

    /**
     * @param CustomerEntity[] $customers
     *
     * @dataProvider getRegistrationValues
     *
     * @throws Exception
     */
    public function testGroupRegistrationAcceptMatches(?int $expectedResCode, ?array $customers, Request $request, ?string $errorMessage): void
    {
        $context = $this->createMock(Context::class);

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
     * @dataProvider getRegistrationValues
     *
     * @throws Exception
     */
    public function testGroupRegistrationDeclineMatches(?int $expectedResCode, ?array $customers, Request $request, ?string $errorMessage): void
    {
        $context = $this->createMock(Context::class);

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
    public function getRegistrationValues(): array
    {
        $customer = $this->createCustomer();
        $customerB = $this->createCustomer();
        $customerWithoutRequest = $this->createCustomer(false);

        return [
            'without user' => [null, null, $this->createRequest([Uuid::randomHex()]), 'Cannot find Customers'],
            'without customer' => [null, null, $this->createRequest([$customer->getId()]),  'Cannot find Customers'],
            'without customerId' => [null, null, $this->createRequest([]), 'customerId or customerIds parameter are missing'],
            'without request group' => [null,  [$customerWithoutRequest], $this->createRequest([$customerWithoutRequest->getId()]), 'User ' . $customerWithoutRequest->getId() . ' dont have approval'],
            'accept/decline' => [204, [$customer], $this->createRequest([$customer->getId()]),  null],
            'accept/decline silent' => [204,  [$customerWithoutRequest], $this->createRequest([$customerWithoutRequest->getId()], true), null],
            'in batch' => [204, [$customer, $customerB], $this->createRequest([$customer->getId(), $customerB->getId()]), null],
        ];
    }

    private function createCustomer(bool $requestedGroup = true): CustomerEntity
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
    private function createRequest(array $customerId, bool $silentError = false): Request
    {
        $request = new Request();
        $request->request->add(['customerIds' => $customerId, 'silentError' => $silentError]);

        return $request;
    }

    private function setSearchReturn(Context $context, ?CustomerCollection $collection = null): void
    {
        if ($collection === null) {
            $collection = new CustomerCollection();
        }
        $criteria = new Criteria(array_values($collection->getIds()));

        $this->customerRepositoryMock->method('search')->withConsecutive(
            [
                $criteria,
                $context,
            ],
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
            $customer->setId($customerId);

            $salesChannelContext->method('getCustomer')->willReturn($customer);
            $salesChannelContext->method('getContext')->willReturn($context);

            return $salesChannelContext;
        });
    }
}
