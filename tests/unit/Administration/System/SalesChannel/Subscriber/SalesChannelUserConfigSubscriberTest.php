<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\System\SalesChannel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\System\SalesChannel\Subscriber\SalesChannelUserConfigSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEvents;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelUserConfigSubscriber::class)]
class SalesChannelUserConfigSubscriberTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<UserConfigCollection>
     */
    private Stub&EntityRepository $userConfigRepository;

    private SalesChannelUserConfigSubscriber $salesChannelUserConfigSubscriber;

    protected function setUp(): void
    {
        $this->userConfigRepository = static::createStub(EntityRepository::class);
        $this->salesChannelUserConfigSubscriber = new SalesChannelUserConfigSubscriber($this->userConfigRepository);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            SalesChannelEvents::SALES_CHANNEL_DELETED => 'onSalesChannelDeleted',
        ], $this->salesChannelUserConfigSubscriber->getSubscribedEvents());
    }

    public function testOnSalesChannelDeletedUpsertWithEmptyArray(): void
    {
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
                ->willReturn(new EntitySearchResult(
                    UserConfigDefinition::ENTITY_NAME,
                    0,
                    new UserConfigCollection([]),
                    null,
                    new Criteria(),
                    $context
                ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([], $context);
        $this->createSubscriber($repository)->onSalesChannelDeleted($event);
    }

    public function testOnSalesChannelDeletedUpsertWithNoSalesChannelId(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        // $userConfig->setValue(['']);
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserConfigDefinition::ENTITY_NAME,
                1,
                new UserConfigCollection([$userConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([], $context);
        $this->createSubscriber($repository)->onSalesChannelDeleted($event);
    }

    public function testOnSalesChannelDeletedUpsertWithNoMatchingId(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue(['test' => '']);
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserConfigDefinition::ENTITY_NAME,
                1,
                new UserConfigCollection([$userConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([], $context);

        $this->createSubscriber($repository)->onSalesChannelDeleted($event);
    }

    public function testOnSalesChannelDeletedUpsertWithMatchingId(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue(['test' => 'test-deleted']);
        $userConfig->setId('test-deleted');
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent(
            'testEntity',
            [new EntityWriteResult(
                'test-deleted',
                [],
                UserConfigDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
            ],
            $context
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserConfigDefinition::ENTITY_NAME,
                1,
                new UserConfigCollection([$userConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([['id' => 'test-deleted', 'value' => []]], $context);
        $this->createSubscriber($repository)->onSalesChannelDeleted($event);
    }

    /**
     * @param MockObject&EntityRepository<UserConfigCollection> $repository
     */
    private function createSubscriber(MockObject&EntityRepository $repository): SalesChannelUserConfigSubscriber
    {
        return new SalesChannelUserConfigSubscriber($repository);
    }
}
