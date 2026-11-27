<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\Subscriber\ProductAdminSearchChangeSetSubscriber;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductAdminSearchChangeSetSubscriber::class)]
class ProductAdminSearchChangeSetSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class, ProductVisibilityDefinition::class, ProductMediaDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'requestChangeSets'],
            ProductAdminSearchChangeSetSubscriber::getSubscribedEvents()
        );
    }

    public function testRequestsChangeSetsOfCommandsWithoutTheProductId(): void
    {
        $visibilityDelete = $this->delete($this->registry->get(ProductVisibilityDefinition::class));
        $mediaDelete = $this->delete($this->registry->get(ProductMediaDefinition::class));
        $visibilityMove = $this->update($this->registry->get(ProductVisibilityDefinition::class), ['sales_channel_id' => Uuid::randomBytes()]);
        $mediaSwap = $this->update($this->registry->get(ProductMediaDefinition::class), ['media_id' => Uuid::randomBytes()]);
        $visibilityValueChange = $this->update($this->registry->get(ProductVisibilityDefinition::class), ['visibility' => 10]);
        $visibilityMoveWithProduct = $this->update($this->registry->get(ProductVisibilityDefinition::class), ['sales_channel_id' => Uuid::randomBytes(), 'product_id' => Uuid::randomBytes()]);
        $productDelete = $this->delete($this->registry->get(ProductDefinition::class));

        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [
            $visibilityDelete,
            $mediaDelete,
            $visibilityMove,
            $mediaSwap,
            $visibilityValueChange,
            $visibilityMoveWithProduct,
            $productDelete,
        ]);

        Feature::withFeatureEnabled('ENABLE_OPENSEARCH_FOR_ADMIN_API', function () use ($event): void {
            $this->subscriber(adminEsEnabled: true)->requestChangeSets($event);
        });

        static::assertTrue($visibilityDelete->requiresChangeSet());
        static::assertTrue($mediaDelete->requiresChangeSet());
        static::assertTrue($visibilityMove->requiresChangeSet());
        static::assertTrue($mediaSwap->requiresChangeSet());
        static::assertFalse($visibilityValueChange->requiresChangeSet());
        static::assertFalse($visibilityMoveWithProduct->requiresChangeSet());
        static::assertFalse($productDelete->requiresChangeSet());
    }

    public function testRequestsNoChangeSetWhenAdminOpenSearchIsDisabled(): void
    {
        $visibilityDelete = $this->delete($this->registry->get(ProductVisibilityDefinition::class));
        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [$visibilityDelete]);

        Feature::withFeatureEnabled('ENABLE_OPENSEARCH_FOR_ADMIN_API', function () use ($event): void {
            $this->subscriber(adminEsEnabled: false)->requestChangeSets($event);
        });

        static::assertFalse($visibilityDelete->requiresChangeSet());
    }

    public function testRequestsNoChangeSetWhenTheAdminApiDoesNotUseOpenSearch(): void
    {
        $visibilityDelete = $this->delete($this->registry->get(ProductVisibilityDefinition::class));
        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [$visibilityDelete]);

        Feature::withFeatureDisabled('ENABLE_OPENSEARCH_FOR_ADMIN_API', function () use ($event): void {
            $this->subscriber(adminEsEnabled: true)->requestChangeSets($event);
        });

        static::assertFalse($visibilityDelete->requiresChangeSet());
    }

    private function subscriber(bool $adminEsEnabled): ProductAdminSearchChangeSetSubscriber
    {
        return new ProductAdminSearchChangeSetSubscriber(
            new AdminElasticsearchHelper($adminEsEnabled, false, 'sw-admin', 'test', false, new NullLogger())
        );
    }

    private function delete(EntityDefinition $definition): DeleteCommand
    {
        $primaryKey = ['id' => Uuid::randomBytes()];

        return new DeleteCommand($definition, $primaryKey, new EntityExistence($definition->getEntityName(), $primaryKey, true, false, false, []));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function update(EntityDefinition $definition, array $payload): UpdateCommand
    {
        $primaryKey = ['id' => Uuid::randomBytes()];

        return new UpdateCommand($definition, $payload, $primaryKey, new EntityExistence($definition->getEntityName(), $primaryKey, true, false, false, []), '');
    }
}
