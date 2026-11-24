<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\Subscriber\ProductAssociationChangeSetSubscriber;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductAssociationChangeSetSubscriber::class)]
class ProductAssociationChangeSetSubscriberTest extends TestCase
{
    private ProductVisibilityDefinition $visibilityDefinition;

    private ProductMediaDefinition $mediaDefinition;

    private ProductTagDefinition $tagDefinition;

    protected function setUp(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                $this->visibilityDefinition = new ProductVisibilityDefinition(),
                $this->mediaDefinition = new ProductMediaDefinition(),
                $this->tagDefinition = new ProductTagDefinition(),
                new ProductDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testHasEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'requestChangeSet'],
            ProductAssociationChangeSetSubscriber::getSubscribedEvents()
        );
    }

    public function testRequestsTheChangeSetOfUpdatedAndDeletedVisibilitiesAndMedia(): void
    {
        $commands = [
            $this->createUpdateCommand($this->visibilityDefinition),
            $this->createDeleteCommand($this->visibilityDefinition),
            $this->createUpdateCommand($this->mediaDefinition),
            $this->createDeleteCommand($this->mediaDefinition),
        ];

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($commands): void {
            $this->dispatch(true, $commands);
        });

        foreach ($commands as $command) {
            static::assertTrue($command->requiresChangeSet());
        }
    }

    public function testDoesNotRequestTheChangeSetOfOtherEntities(): void
    {
        $command = $this->createDeleteCommand($this->tagDefinition);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($command): void {
            $this->dispatch(true, [$command]);
        });

        static::assertFalse($command->requiresChangeSet());
    }

    public function testDoesNotRequestTheChangeSetWhenTheAdminSearchIsDisabled(): void
    {
        $command = $this->createDeleteCommand($this->visibilityDefinition);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($command): void {
            $this->dispatch(false, [$command]);
        });

        static::assertFalse($command->requiresChangeSet());
    }

    public function testDoesNotRequestTheChangeSetWithoutTheOpenSearchAdminApi(): void
    {
        $command = $this->createDeleteCommand($this->visibilityDefinition);

        Feature::withFeatureDisabled('ENABLE_OPENSEARCH_FOR_ADMIN_API', function () use ($command): void {
            $this->dispatch(true, [$command]);
        });

        static::assertFalse($command->requiresChangeSet());
    }

    private function createUpdateCommand(EntityDefinition $definition): UpdateCommand
    {
        return new UpdateCommand($definition, [], ['id' => Uuid::randomBytes()], static::createStub(EntityExistence::class), '');
    }

    private function createDeleteCommand(EntityDefinition $definition): DeleteCommand
    {
        return new DeleteCommand($definition, ['id' => Uuid::randomBytes()], static::createStub(EntityExistence::class));
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function dispatch(bool $adminEsEnabled, array $commands): void
    {
        $subscriber = new ProductAssociationChangeSetSubscriber(
            new AdminElasticsearchHelper($adminEsEnabled, false, 'sw-admin', 'test', false, new NullLogger())
        );

        $subscriber->requestChangeSet(new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands,
        ));
    }
}
