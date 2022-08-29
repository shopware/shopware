<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes\FakeQueryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController
 */
class CustomerGroupRegistrationActionControllerTest extends TestCase
{
    private CustomerGroupRegistrationActionController $controllerMock;

    /**
     * @var EntityRepository|MockObject
     */
    private $repositoryMock;

    /**
     * @var EventDispatcher|MockObject
     */
    private $eventDispatcherMock;

    /**
     * @var MockObject|Connection
     */
    private $connectionMock;

    public function setUp(): void
    {
        $this->repositoryMock = $this->createMock(EntityRepository::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $contextRestorerMock = $this->createMock(SalesChannelContextRestorer::class);
        $this->connectionMock = $this->createMock(Connection::class);

        $this->controllerMock = new CustomerGroupRegistrationActionController(
            $this->repositoryMock,
            $this->eventDispatcherMock,
            $contextRestorerMock,
            $this->connectionMock,
        );
    }

    public function testAcceptRouteWithMissingCustomerLanguageId(): void
    {
        $customer = $this->createCustomer(false);
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = Context::createDefaultContext();
        $this->setQueryBuilderReturn(null);
        $this->setSearchReturn($criteria, $context);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('languageId not found for customer with the id: "' . $customer->getId() . '"');
        $this->controllerMock->accept($request, $context);
    }

    public function testAcceptRouteWithoutCustomerId(): void
    {
        $context = Context::createDefaultContext();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('customerId or customerIds parameter are missing');
        $this->controllerMock->accept(new Request(), $context);
    }

    public function testAcceptRouteWithoutRequestGroup(): void
    {
        $customer = $this->createCustomer(false);
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $this->setQueryBuilderReturn($customer->getLanguageId());
        $this->setSearchReturn($criteria, $context, $customerCollection);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User ' . $customer->getId() . ' dont have approval');
        $this->controllerMock->accept($request, $context);
    }

    public function testAccept(): void
    {
        $customer = $this->createCustomer();
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $this->setSearchReturn($criteria, $context, $customerCollection);
        $this->setQueryBuilderReturn($customer->getLanguageId());

        $this->repositoryMock->method('update')->willReturnCallback(function ($updateData): EntityWrittenContainerEvent {
            static::assertNotNull($updateData);
            static::assertIsArray($updateData[0]);
            static::assertNull($updateData[0]['requestedGroupId']);
            static::assertNotNull($updateData[0]['groupId']);

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame(204, $res->getStatusCode());
    }

    public function testAcceptInBatch(): void
    {
        $langId = Uuid::randomHex();
        $customerA = $this->createCustomer(true, $langId);
        $customerB = $this->createCustomer(true, $langId);
        $criteria = $this->createCriteria([$customerA->getId(), $customerB->getId()]);
        $request = $this->createRequest([$customerA->getId(), $customerB->getId()]);
        $context = $this->createContext([$customerA->getLanguageId(), $customerB->getLanguageId()]);

        $this->connectionMock->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($this->connectionMock, [$langId]),
            new FakeQueryBuilder($this->connectionMock, [$langId])
        );

        $this->repositoryMock->method('search')->willReturnCallback(function (Criteria $searchCriteria) use ($context, $criteria, $customerA, $customerB): EntitySearchResult {
            $customer = $searchCriteria->getIds()[0] === $customerA->getId() ? $customerB : $customerA;

            return new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                $criteria,
                $context
            );
        });

        $this->repositoryMock->method('update')->willReturnCallback(function ($updateData): EntityWrittenContainerEvent {
            static::assertNotNull($updateData);
            foreach ($updateData as $customer) {
                static::assertIsArray($customer);
                static::assertNull($customer['requestedGroupId']);
                static::assertNotNull($customer['groupId']);
            }

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame(204, $res->getStatusCode());
    }

    public function testDecline(): void
    {
        $customer = $this->createCustomer();
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $this->setSearchReturn($criteria, $context, $customerCollection);
        $this->setQueryBuilderReturn($customer->getLanguageId());

        $this->repositoryMock->method('update')->willReturnCallback(function ($updateData): EntityWrittenContainerEvent {
            static::assertNotNull($updateData);
            static::assertIsArray($updateData[0]);
            static::assertNull($updateData[0]['requestedGroupId']);
            static::assertArrayNotHasKey('groupId', $updateData[0]);

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame(204, $res->getStatusCode());
    }

    public function testDeclineInBatch(): void
    {
        $langId = Uuid::randomHex();
        $customerA = $this->createCustomer(true, $langId);
        $customerB = $this->createCustomer(true, $langId);
        $criteria = $this->createCriteria([$customerA->getId(), $customerB->getId()]);
        $request = $this->createRequest([$customerA->getId(), $customerB->getId()]);
        $context = $this->createContext([$customerA->getLanguageId(), $customerB->getLanguageId()]);

        $this->connectionMock->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($this->connectionMock, [$langId]),
            new FakeQueryBuilder($this->connectionMock, [$langId])
        );

        $this->repositoryMock->method('search')->willReturnCallback(function (Criteria $searchCriteria) use ($context, $criteria, $customerA, $customerB): EntitySearchResult {
            $customer = $searchCriteria->getIds()[0] === $customerA->getId() ? $customerB : $customerA;

            return new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                $criteria,
                $context
            );
        });

        $this->repositoryMock->method('update')->willReturnCallback(function ($updateData): EntityWrittenContainerEvent {
            static::assertNotNull($updateData);
            foreach ($updateData as $customer) {
                static::assertIsArray($customer);
                static::assertNull($customer['requestedGroupId']);
                static::assertArrayNotHasKey('groupId', $customer);
            }

            return $this->createMock(EntityWrittenContainerEvent::class);
        });

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame(204, $res->getStatusCode());
    }

    public function testAcceptWithSilentError(): void
    {
        $customer = $this->createCustomer(false);
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $this->setQueryBuilderReturn($customer->getLanguageId());
        $this->setSearchReturn($criteria, $context, $customerCollection);

        $this->expectException(\Exception::class);
        $res = $this->controllerMock->accept($request, $context);
        static::assertSame(Response::HTTP_NO_CONTENT, $res->getStatusCode());

        $customer = $this->createCustomer(false);
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()], true);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollectionB = new CustomerCollection([$customer]);
        $this->setQueryBuilderReturn($customer->getLanguageId());
        $this->setSearchReturn($criteria, $context, $customerCollectionB);

        $resB = $this->controllerMock->accept($request, $context);
        static::assertSame(Response::HTTP_NO_CONTENT, $resB->getStatusCode());
    }

    public function testDeclineWithSilentError(): void
    {
        $customer = $this->createCustomer(false);
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $this->setQueryBuilderReturn($customer->getLanguageId());
        $this->setSearchReturn($criteria, $context, $customerCollection);

        $this->expectException(\Exception::class);
        $res = $this->controllerMock->decline($request, $context);
        static::assertSame(Response::HTTP_NO_CONTENT, $res->getStatusCode());

        $customer = $this->createCustomer(false);
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()], true);
        $context = $this->createContext([$customer->getLanguageId()]);

        $customerCollectionB = new CustomerCollection([$customer]);
        $this->setQueryBuilderReturn($customer->getLanguageId());
        $this->setSearchReturn($criteria, $context, $customerCollectionB);

        $resB = $this->controllerMock->decline($request, $context);
        static::assertSame(Response::HTTP_NO_CONTENT, $resB->getStatusCode());
    }

    public function testEventDispatchCustomerRegistrationAcceptedEventCorrectLanguageContext(): void
    {
        $customer = $this->createCustomer();
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([Uuid::randomHex()]);
        $customerContext = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = new EntitySearchResult(
            CustomerDefinition::ENTITY_NAME,
            $customerCollection->count(),
            $customerCollection,
            null,
            $criteria,
            $customerContext
        );
        $this->setQueryBuilderReturn($customer->getLanguageId());

        $this->repositoryMock->method('search')->willReturnCallback(function (Criteria $criteria, Context $searchContext) use ($customerContext, $searchResult): EntitySearchResult {
            static::assertNotNull($searchContext);
            static::assertSame($customerContext->getLanguageIdChain(), $searchContext->getLanguageIdChain());

            return $searchResult;
        });

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame(Response::HTTP_NO_CONTENT, $res->getStatusCode());
    }

    public function testEventDispatchCustomerRegistrationDeclinedEventCorrectLanguageContext(): void
    {
        $customer = $this->createCustomer();
        $criteria = $this->createCriteria([$customer->getId()]);
        $request = $this->createRequest([$customer->getId()]);
        $context = $this->createContext([Uuid::randomHex()]);
        $customerContext = $this->createContext([$customer->getLanguageId()]);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = new EntitySearchResult(
            CustomerDefinition::ENTITY_NAME,
            $customerCollection->count(),
            $customerCollection,
            null,
            $criteria,
            $customerContext
        );
        $this->setQueryBuilderReturn($customer->getLanguageId());

        $this->repositoryMock->method('search')->willReturnCallback(function (Criteria $criteria, Context $searchContext) use ($customerContext, $searchResult): EntitySearchResult {
            static::assertNotNull($searchContext);
            static::assertSame($customerContext->getLanguageIdChain(), $searchContext->getLanguageIdChain());

            return $searchResult;
        });

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame(Response::HTTP_NO_CONTENT, $res->getStatusCode());
    }

    private function createCustomer(bool $requestedGroup = true, ?string $langId = null): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setLanguageId($langId ?? Uuid::randomHex());

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
    private function createCriteria(array $customerId): Criteria
    {
        $criteria = new Criteria($customerId);
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('salutation');

        return $criteria;
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

    /**
     * @param string[] $customerIds
     */
    private function createContext(array $customerIds): Context
    {
        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => $customerIds,
        ]);

        return $context;
    }

    /**
     * @param mixed $value
     */
    private function setQueryBuilderReturn($value): void
    {
        $this->connectionMock->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($this->connectionMock, [$value]),
        );
    }

    private function setSearchReturn(Criteria $criteria, Context $context, ?CustomerCollection $collection = null): void
    {
        if ($collection === null) {
            $collection = new CustomerCollection();
        }

        $this->repositoryMock->method('search')->withConsecutive(
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
}
