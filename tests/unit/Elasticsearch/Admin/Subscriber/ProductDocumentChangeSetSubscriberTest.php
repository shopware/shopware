<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
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
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Admin\Subscriber\ProductDocumentChangeSetSubscriber;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentChangeSetSubscriber::class)]
class ProductDocumentChangeSetSubscriberTest extends TestCase
{
    private ProductDocumentDefinition $documentDefinition;

    private TaxDefinition $taxDefinition;

    protected function setUp(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                $this->documentDefinition = new ProductDocumentDefinition(),
                $this->taxDefinition = new TaxDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testHasEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'requestChangeSet'],
            ProductDocumentChangeSetSubscriber::getSubscribedEvents()
        );
    }

    public function testRequestsChangeSetForDeletedDocuments(): void
    {
        $command = $this->deleteCommand($this->documentDefinition);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], fn () => $this->dispatch($command));

        static::assertTrue($command->requiresChangeSet());
    }

    public function testIgnoresDeletedDocumentsWithoutAdminOpenSearch(): void
    {
        $command = $this->deleteCommand($this->documentDefinition);

        Feature::fake([], function () use ($command): void {
            $_SERVER['ENABLE_OPENSEARCH_FOR_ADMIN_API'] = false;

            $this->dispatch($command);
        });

        static::assertFalse($command->requiresChangeSet());
    }

    public function testIgnoresDocumentUpdates(): void
    {
        $command = new UpdateCommand(
            $this->documentDefinition,
            ['position' => 2],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0',
        );

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], fn () => $this->dispatch($command));

        static::assertFalse($command->requiresChangeSet());
    }

    public function testIgnoresOtherEntities(): void
    {
        $command = $this->deleteCommand($this->taxDefinition);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], fn () => $this->dispatch($command));

        static::assertFalse($command->requiresChangeSet());
    }

    private function deleteCommand(EntityDefinition $definition): DeleteCommand
    {
        return new DeleteCommand($definition, ['id' => Uuid::randomBytes()], static::createStub(EntityExistence::class));
    }

    private function dispatch(WriteCommand $command): void
    {
        (new ProductDocumentChangeSetSubscriber())->requestChangeSet(new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        ));
    }
}
