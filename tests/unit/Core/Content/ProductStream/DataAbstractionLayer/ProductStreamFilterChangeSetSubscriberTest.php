<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamFilterChangeSetSubscriber;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStreamFilterChangeSetSubscriber::class)]
class ProductStreamFilterChangeSetSubscriberTest extends TestCase
{
    private ProductStreamFilterChangeSetSubscriber $subscriber;

    private IdsCollection $ids;

    private ProductStreamFilterDefinition $filterDefinition;

    private ProductStreamDefinition $streamDefinition;

    protected function setUp(): void
    {
        $this->subscriber = new ProductStreamFilterChangeSetSubscriber();
        $this->ids = new IdsCollection();

        new StaticDefinitionInstanceRegistry(
            [
                $this->filterDefinition = new ProductStreamFilterDefinition(),
                $this->streamDefinition = new ProductStreamDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testHasEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'triggerChangeSet'],
            ProductStreamFilterChangeSetSubscriber::getSubscribedEvents()
        );
    }

    public function testRequestsChangeSetForDeletedFilter(): void
    {
        $command = new DeleteCommand(
            $this->filterDefinition,
            ['id' => $this->ids->getBytes('filter')],
            static::createStub(EntityExistence::class),
        );

        $this->dispatch($command);

        static::assertTrue($command->requiresChangeSet());
    }

    public function testRequestsChangeSetForUpdateWithoutStreamIdInPayload(): void
    {
        $command = new UpdateCommand(
            $this->filterDefinition,
            ['value' => '5'],
            ['id' => $this->ids->getBytes('filter')],
            static::createStub(EntityExistence::class),
            '/0',
        );

        $this->dispatch($command);

        static::assertTrue($command->requiresChangeSet());
    }

    /**
     * A reassignment carries the new stream in its payload, but the stream losing the filter is only
     * recoverable from the previous row state, so this write needs a change set too.
     */
    public function testRequestsChangeSetForUpdateThatReassignsTheFilter(): void
    {
        $command = new UpdateCommand(
            $this->filterDefinition,
            ['product_stream_id' => Uuid::fromHexToBytes($this->ids->get('new-stream')), 'value' => '5'],
            ['id' => $this->ids->getBytes('filter')],
            static::createStub(EntityExistence::class),
            '/0',
        );

        $this->dispatch($command);

        static::assertTrue($command->requiresChangeSet());
    }

    public function testIgnoresOtherEntities(): void
    {
        $command = new DeleteCommand(
            $this->streamDefinition,
            ['id' => Uuid::fromHexToBytes($this->ids->get('stream'))],
            static::createStub(EntityExistence::class),
        );

        $this->dispatch($command);

        static::assertFalse($command->requiresChangeSet());
    }

    private function dispatch(WriteCommand $command): void
    {
        $this->subscriber->triggerChangeSet(new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        ));
    }
}
