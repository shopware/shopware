<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingListener;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEvents;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingListener::class)]
class SalesChannelTrackingListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = SalesChannelTrackingListener::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
        static::assertSame('createTrackingRecords', $events[EntityWrittenContainerEvent::class]);
        static::assertArrayHasKey(SalesChannelEvents::SALES_CHANNEL_WRITTEN, $events);
        static::assertSame('invalidateTrackableChannelCache', $events[SalesChannelEvents::SALES_CHANNEL_WRITTEN]);
        static::assertArrayHasKey(SalesChannelEvents::SALES_CHANNEL_DELETED, $events);
        static::assertSame('invalidateTrackableChannelCache', $events[SalesChannelEvents::SALES_CHANNEL_DELETED]);
    }

    public function testStoreReferralCodeDoesNothingForNonStorefrontRoute(): void
    {
        $channelId = Uuid::randomHex();
        $listener = $this->createListener(salesChannelIds: [$channelId]);

        $request = new Request(query: [SalesChannelTrackingListener::QUERY_PARAM => $channelId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['api']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $listener->storeReferralCode($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    public function testStoreReferralCodeDoesNothingIfRequestHasNoSession(): void
    {
        $this->expectNotToPerformAssertions();

        $channelId = Uuid::randomHex();
        $listener = $this->createListener(salesChannelIds: [$channelId]);

        $request = new Request(query: [SalesChannelTrackingListener::QUERY_PARAM => $channelId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);

        // No session set on request — must not throw
        $listener->storeReferralCode($this->createControllerEvent($request));
    }

    public function testStoreReferralCodeSkipsLazySessionWithoutInitializingIt(): void
    {
        $channelId = Uuid::randomHex();
        $listener = $this->createListener(salesChannelIds: [$channelId]);

        $request = new Request(query: [SalesChannelTrackingListener::QUERY_PARAM => $channelId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);
        $request->setSessionFactory(static function (): Session {
            throw new \RuntimeException('Session should not be initialized.');
        });

        $listener->storeReferralCode($this->createControllerEvent($request));

        static::assertFalse($request->hasSession(true));
    }

    public function testStoreReferralCodeDoesNothingForInvalidUuid(): void
    {
        $listener = $this->createListener();

        $request = $this->createStorefrontRequest(queryParam: 'not-a-uuid');

        $listener->storeReferralCode($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    public function testStoreReferralCodeDoesNothingForMissingQueryParam(): void
    {
        $listener = $this->createListener();

        $request = $this->createStorefrontRequest();

        $listener->storeReferralCode($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    public function testStoreReferralCodeDoesNothingForNonTrackableChannel(): void
    {
        $listener = $this->createListener();

        $channelId = Uuid::randomHex();
        $request = $this->createStorefrontRequest(queryParam: $channelId);

        $listener->storeReferralCode($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    public function testStoreReferralCodeStoresInSession(): void
    {
        $channelId = Uuid::randomHex();
        $listener = $this->createListener(salesChannelIds: [$channelId]);

        $request = $this->createStorefrontRequest(queryParam: $channelId);

        $listener->storeReferralCode($this->createControllerEvent($request));

        $sessionData = $request->getSession()->get(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE);
        static::assertSame($channelId, $sessionData);
    }

    public function testCreateTrackingRecordsSkipsNonLiveVersion(): void
    {
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $listener = $this->createListener(orderRepo: $orderRepo);

        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());
        $event = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $listener->createTrackingRecords($event);

        static::assertCount(0, $orderRepo->upserts);
    }

    public function testCreateTrackingRecordsSkipsIfNoRequest(): void
    {
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: null);

        $event = $this->createContainerEvent(OrderDefinition::ENTITY_NAME, [Uuid::randomHex()]);

        $listener->createTrackingRecords($event);

        static::assertCount(0, $orderRepo->upserts);
    }

    public function testCreateTrackingRecordsSkipsUnrelatedEntityWithoutInitializingSession(): void
    {
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $request = new Request();
        $request->setSessionFactory(static function (): Session {
            throw new \RuntimeException('Session should not be initialized.');
        });

        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: $request);

        $event = $this->createContainerEvent('scheduled_task', [Uuid::randomHex()]);

        $listener->createTrackingRecords($event);

        static::assertFalse($request->hasSession(true));
        static::assertCount(0, $orderRepo->upserts);
    }

    public function testCreateTrackingRecordsSkipsLazySessionWithoutInitializingIt(): void
    {
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $request = new Request();
        $request->setSessionFactory(static function (): Session {
            throw new \RuntimeException('Session should not be initialized.');
        });

        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: $request);

        $event = $this->createContainerEvent(OrderDefinition::ENTITY_NAME, [Uuid::randomHex()]);

        $listener->createTrackingRecords($event);

        static::assertFalse($request->hasSession(true));
        static::assertCount(0, $orderRepo->upserts);
    }

    public function testCreateTrackingRecordsSkipsIfNoReferralCodeInSession(): void
    {
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $request = $this->createStorefrontRequest();
        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: $request);

        $event = $this->createContainerEvent(OrderDefinition::ENTITY_NAME, [Uuid::randomHex()]);

        $listener->createTrackingRecords($event);

        static::assertCount(0, $orderRepo->upserts);
    }

    public function testCreateTrackingRecordsWritesOrderRecord(): void
    {
        $orderId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: $request);

        $event = $this->createContainerEvent(OrderDefinition::ENTITY_NAME, [$orderId]);

        $listener->createTrackingRecords($event);

        static::assertCount(1, $orderRepo->upserts);
        $record = $orderRepo->upserts[0][0];
        static::assertArrayHasKey('orderId', $record);
        static::assertSame($orderId, $record['orderId']);
        static::assertArrayHasKey('orderVersionId', $record);
        static::assertSame(Defaults::LIVE_VERSION, $record['orderVersionId']);
        static::assertArrayHasKey('salesChannelId', $record);
        static::assertSame($channelId, $record['salesChannelId']);
        static::assertArrayHasKey('id', $record);
        static::assertTrue(Uuid::isValid($record['id']));
    }

    public function testCreateTrackingRecordsWritesCustomerRecord(): void
    {
        $customerId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        /** @var StaticEntityRepository<SalesChannelTrackingCustomerCollection> $customerRepo */
        $customerRepo = new StaticEntityRepository([new SalesChannelTrackingCustomerCollection()]);

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $listener = $this->createListener(customerRepo: $customerRepo, mainRequest: $request);

        $event = $this->createContainerEvent(CustomerDefinition::ENTITY_NAME, [$customerId]);

        $listener->createTrackingRecords($event);

        static::assertCount(1, $customerRepo->upserts);
        $record = $customerRepo->upserts[0][0];
        static::assertArrayHasKey('customerId', $record);
        static::assertSame($customerId, $record['customerId']);
        static::assertArrayHasKey('salesChannelId', $record);
        static::assertSame($channelId, $record['salesChannelId']);
        static::assertArrayHasKey('id', $record);
        static::assertTrue(Uuid::isValid($record['id']));
    }

    public function testCreateTrackingRecordsSkipsNonInsertOperations(): void
    {
        $channelId = Uuid::randomHex();

        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: $request);

        $writeResult = new EntityWriteResult(
            Uuid::randomHex(),
            [],
            OrderDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        );
        $orderEvent = new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [$writeResult], Context::createDefaultContext());
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$orderEvent]),
            [],
        );

        $listener->createTrackingRecords($event);

        static::assertCount(0, $orderRepo->upserts);
    }

    public function testCreateTrackingRecordsLogsWarningOnException(): void
    {
        $channelId = Uuid::randomHex();

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->method('upsert')->willThrowException(new \RuntimeException('DB error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('failed to write order tracking record'));

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepo */
        $salesChannelRepo = new StaticEntityRepository([new SalesChannelCollection()]);
        /** @var StaticEntityRepository<SalesChannelTrackingCustomerCollection> $customerRepo */
        $customerRepo = new StaticEntityRepository([new SalesChannelTrackingCustomerCollection()]);

        $listener = new SalesChannelTrackingListener(
            $salesChannelRepo,
            $orderRepo,
            $customerRepo,
            $logger,
            $requestStack,
            new TagAwareAdapter(new ArrayAdapter()),
        );

        $event = $this->createContainerEvent(OrderDefinition::ENTITY_NAME, [Uuid::randomHex()]);

        $listener->createTrackingRecords($event);
    }

    public function testCreateTrackingRecordsLogsWarningOnCustomerException(): void
    {
        $channelId = Uuid::randomHex();

        $customerRepo = $this->createMock(EntityRepository::class);
        $customerRepo->method('upsert')->willThrowException(new \RuntimeException('DB error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('failed to write customer tracking record'));

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepo */
        $salesChannelRepo = new StaticEntityRepository([new SalesChannelCollection()]);
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $listener = new SalesChannelTrackingListener(
            $salesChannelRepo,
            $orderRepo,
            $customerRepo,
            $logger,
            $requestStack,
            new TagAwareAdapter(new ArrayAdapter()),
        );

        $event = $this->createContainerEvent(CustomerDefinition::ENTITY_NAME, [Uuid::randomHex()]);

        $listener->createTrackingRecords($event);
    }

    public function testCreateTrackingRecordsHandlesArrayPrimaryKey(): void
    {
        $channelId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $listener = $this->createListener(orderRepo: $orderRepo, mainRequest: $request);

        $context = Context::createDefaultContext();
        $writeResult = new EntityWriteResult(
            ['id' => $orderId, 'versionId' => Defaults::LIVE_VERSION],
            [],
            OrderDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [$writeResult], $context)]),
            [],
        );

        $listener->createTrackingRecords($event);

        static::assertCount(1, $orderRepo->upserts);
        $record = $orderRepo->upserts[0][0];
        static::assertArrayHasKey('orderId', $record);
        static::assertSame($orderId, $record['orderId']);
    }

    public function testCreateTrackingRecordsSkipsCustomerNonInsertOperations(): void
    {
        $channelId = Uuid::randomHex();

        /** @var StaticEntityRepository<SalesChannelTrackingCustomerCollection> $customerRepo */
        $customerRepo = new StaticEntityRepository([new SalesChannelTrackingCustomerCollection()]);

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $listener = $this->createListener(customerRepo: $customerRepo, mainRequest: $request);

        $writeResult = new EntityWriteResult(
            Uuid::randomHex(),
            [],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        );
        $customerEvent = new EntityWrittenEvent(CustomerDefinition::ENTITY_NAME, [$writeResult], Context::createDefaultContext());
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$customerEvent]),
            [],
        );

        $listener->createTrackingRecords($event);

        static::assertCount(0, $customerRepo->upserts);
    }

    public function testCreateTrackingRecordsWritesBothOrderAndCustomerInSameEvent(): void
    {
        $orderId = Uuid::randomHex();
        $customerId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);
        /** @var StaticEntityRepository<SalesChannelTrackingCustomerCollection> $customerRepo */
        $customerRepo = new StaticEntityRepository([new SalesChannelTrackingCustomerCollection()]);

        $request = $this->createStorefrontRequest();
        $request->getSession()->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $channelId);

        $listener = $this->createListener(orderRepo: $orderRepo, customerRepo: $customerRepo, mainRequest: $request);

        $context = Context::createDefaultContext();
        $orderWriteResult = new EntityWriteResult($orderId, [], OrderDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT);
        $customerWriteResult = new EntityWriteResult($customerId, [], CustomerDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT);

        $event = new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [$orderWriteResult], $context),
            new EntityWrittenEvent(CustomerDefinition::ENTITY_NAME, [$customerWriteResult], $context),
        ]), []);

        $listener->createTrackingRecords($event);

        static::assertCount(1, $orderRepo->upserts);
        $orderRecord = $orderRepo->upserts[0][0];
        static::assertArrayHasKey('orderId', $orderRecord);
        static::assertSame($orderId, $orderRecord['orderId']);

        static::assertCount(1, $customerRepo->upserts);
        $customerRecord = $customerRepo->upserts[0][0];
        static::assertArrayHasKey('customerId', $customerRecord);
        static::assertSame($customerId, $customerRecord['customerId']);
    }

    public function testInvalidateTrackableChannelCachePurgesCachedResult(): void
    {
        $channelId = Uuid::randomHex();
        $cache = new TagAwareAdapter(new ArrayAdapter());

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepo */
        $salesChannelRepo = new StaticEntityRepository([[], [$channelId]]);
        $requestStack = new RequestStack();
        $request = $this->createStorefrontRequest($channelId);
        $requestStack->push($request);

        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo = new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);
        /** @var StaticEntityRepository<SalesChannelTrackingCustomerCollection> $customerRepo */
        $customerRepo = new StaticEntityRepository([new SalesChannelTrackingCustomerCollection()]);

        $listener = new SalesChannelTrackingListener(
            $salesChannelRepo,
            $orderRepo,
            $customerRepo,
            new NullLogger(),
            $requestStack,
            $cache,
        );

        $listener->storeReferralCode($this->createControllerEvent($request));
        static::assertFalse($request->getSession()->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));

        $context = Context::createDefaultContext();
        $writeResult = new EntityWriteResult($channelId, [], 'sales_channel', EntityWriteResult::OPERATION_UPDATE);
        $writtenEvent = new EntityWrittenEvent('sales_channel', [$writeResult], $context);
        $listener->invalidateTrackableChannelCache($writtenEvent);

        $listener->storeReferralCode($this->createControllerEvent($request));
        static::assertTrue($request->getSession()->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    /**
     * @param list<string> $salesChannelIds
     * @param StaticEntityRepository<SalesChannelTrackingOrderCollection>|null $orderRepo
     * @param StaticEntityRepository<SalesChannelTrackingCustomerCollection>|null $customerRepo
     */
    private function createListener(
        array $salesChannelIds = [],
        ?StaticEntityRepository $orderRepo = null,
        ?StaticEntityRepository $customerRepo = null,
        Request|false|null $mainRequest = false,
        ?TagAwareCacheInterface $cache = null,
    ): SalesChannelTrackingListener {
        /** @var StaticEntityRepository<SalesChannelTrackingOrderCollection> $orderRepo */
        $orderRepo ??= new StaticEntityRepository([new SalesChannelTrackingOrderCollection()]);
        /** @var StaticEntityRepository<SalesChannelTrackingCustomerCollection> $customerRepo */
        $customerRepo ??= new StaticEntityRepository([new SalesChannelTrackingCustomerCollection()]);

        $requestStack = new RequestStack();
        if ($mainRequest !== false && $mainRequest !== null) {
            $requestStack->push($mainRequest);
        }

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepo */
        $salesChannelRepo = new StaticEntityRepository([$salesChannelIds]);

        return new SalesChannelTrackingListener(
            $salesChannelRepo,
            $orderRepo,
            $customerRepo,
            new NullLogger(),
            $requestStack,
            $cache ?? new TagAwareAdapter(new ArrayAdapter()),
        );
    }

    private function createStorefrontRequest(?string $queryParam = null): Request
    {
        $query = $queryParam !== null ? [SalesChannelTrackingListener::QUERY_PARAM => $queryParam] : [];
        $request = new Request(query: $query);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function createControllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => new \stdClass(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    /**
     * @param list<string> $entityIds
     */
    private function createContainerEvent(string $entityName, array $entityIds): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();

        $writeResults = array_map(
            static fn (string $id): EntityWriteResult => new EntityWriteResult($id, [], $entityName, EntityWriteResult::OPERATION_INSERT),
            $entityIds,
        );

        $writtenEvent = new EntityWrittenEvent($entityName, $writeResults, $context);

        return new EntityWrittenContainerEvent($context, new NestedEventCollection([$writtenEvent]), []);
    }
}
