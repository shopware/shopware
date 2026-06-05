<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Event\CustomerCreatedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerUpdatedEvent;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerBusinessEventSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerBusinessEventSubscriber::class)]
class CustomerBusinessEventSubscriberTest extends TestCase
{
    public function testCustomerInsertDispatchesCreatedEventWithoutLoadingTheCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<CustomerCreatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(CustomerCreatedEvent::class, static function (CustomerCreatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // empty repository: a search would fail loudly, proving the lazy load stays unexecuted
        $subscriber->onEntityWritten($this->createContainer($context, new EntityWriteResult(
            $customerId,
            ['id' => $customerId, 'salesChannelId' => $salesChannelId],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT
        )));

        static::assertCount(1, $caught);
        static::assertSame($customerId, $caught[0]->getCustomerId());
        static::assertSame($salesChannelId, $caught[0]->getSalesChannelId());
        static::assertSame(['customerId' => $customerId], $caught[0]->getValues());
    }

    public function testCustomerInsertFromCliImportContextDispatchesCreatedEvent(): void
    {
        $customerId = Uuid::randomHex();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        $caught = 0;
        $dispatcher->addListener(CustomerCreatedEvent::class, static function () use (&$caught): void {
            ++$caught;
        });

        $subscriber->onEntityWritten($this->createContainer(Context::createCLIContext(), new EntityWriteResult(
            $customerId,
            ['id' => $customerId],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT
        )));

        static::assertSame(1, $caught);
    }

    public function testCustomerUpdateDispatchesUpdatedEventWithChangedFields(): void
    {
        $customerId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<CustomerUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(CustomerUpdatedEvent::class, static function (CustomerUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWriteResult(
            $customerId,
            ['updatedAt' => '2024-01-01', 'email' => 'changed@example.com'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        )));

        static::assertCount(1, $caught);
        static::assertSame($customerId, $caught[0]->getCustomerId());
        static::assertSame(['email'], $caught[0]->getChangedFields());
        static::assertSame(['customerId' => $customerId, 'changedFields' => ['email']], $caught[0]->getValues());
    }

    public function testUpdatesTouchingOnlySystemFieldsStaySilent(): void
    {
        [$subscriber, $dispatcher] = $this->createSubscriber();

        $caught = 0;
        $dispatcher->addListener(CustomerUpdatedEvent::class, static function () use (&$caught): void {
            ++$caught;
        });

        $subscriber->onEntityWritten($this->createContainer(Context::createDefaultContext(), new EntityWriteResult(
            Uuid::randomHex(),
            ['updatedAt' => '2024-01-01'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        )));

        static::assertSame(0, $caught);
    }

    /**
     * @return array{0: CustomerBusinessEventSubscriber, 1: EventDispatcher}
     */
    private function createSubscriber(): array
    {
        $definition = new CustomerDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([], $definition);

        $dispatcher = new EventDispatcher();
        $subscriber = new CustomerBusinessEventSubscriber(
            $customerRepository,
            $dispatcher
        );

        return [$subscriber, $dispatcher];
    }

    private function createContainer(Context $context, EntityWriteResult $writeResult): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(CustomerDefinition::ENTITY_NAME, [$writeResult], $context),
        ]), []);
    }
}
