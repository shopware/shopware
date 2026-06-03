<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Event\DocumentDeletedEvent;
use Shopware\Core\Checkout\Document\Subscriber\DocumentBusinessEventSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentBusinessEventSubscriber::class)]
class DocumentBusinessEventSubscriberTest extends TestCase
{
    public function testDocumentDeleteDispatchesDeletedEventOnlyAfterDeleteSucceeds(): void
    {
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $document = (new DocumentEntity())->assign([
            'id' => $documentId,
            'orderId' => $orderId,
            'documentNumber' => '1000',
        ]);

        $documentDefinition = $this->createDocumentDefinition();

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentEntity::class,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria([$documentId]),
                $context,
            ),
        ], $documentDefinition);

        $dispatcher = new EventDispatcher();
        $subscriber = new DocumentBusinessEventSubscriber($documentRepository, $dispatcher);

        /** @var list<DocumentDeletedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(DocumentDeletedEvent::class, static function (DocumentDeletedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $deleteEvent = $this->createEntityDeleteEvent($documentDefinition, $documentId);
        $subscriber->beforeDelete($deleteEvent);

        static::assertCount(0, $caught, 'deleted event must not fire before the delete succeeded');

        $deleteEvent->success();

        static::assertCount(1, $caught);
        static::assertSame($documentId, $caught[0]->getDocumentId());
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame('1000', $caught[0]->getDocumentNumber());
        static::assertNotSame('', $caught[0]->getDeletedAt());
    }

    public function testOnlySubscribesToTheDeleteEvent(): void
    {
        // generated is dispatched by DocumentGenerator; this subscriber only observes deletes
        static::assertSame(
            [EntityDeleteEvent::class => 'beforeDelete'],
            DocumentBusinessEventSubscriber::getSubscribedEvents()
        );
    }

    public function testNonLiveVersionContextDeletesAreIgnored(): void
    {
        $versionContext = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());
        $documentDefinition = $this->createDocumentDefinition();

        $dispatcher = new EventDispatcher();
        // empty repository: a search would fail loudly, proving no lookup happens
        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([], $documentDefinition);
        $subscriber = new DocumentBusinessEventSubscriber(
            $documentRepository,
            $dispatcher
        );

        $caught = 0;
        $dispatcher->addListener(DocumentDeletedEvent::class, static function () use (&$caught): void {
            ++$caught;
        });

        $deleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext($versionContext),
            [$this->createDeleteCommand($documentDefinition, Uuid::randomHex())]
        );

        $subscriber->beforeDelete($deleteEvent);
        $deleteEvent->success();

        static::assertSame(0, $caught);
    }

    private function createDocumentDefinition(): DocumentDefinition
    {
        $definition = new DocumentDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        return $definition;
    }

    private function createEntityDeleteEvent(DocumentDefinition $definition, string $documentId): EntityDeleteEvent
    {
        return EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$this->createDeleteCommand($definition, $documentId)]
        );
    }

    private function createDeleteCommand(DocumentDefinition $definition, string $documentId): DeleteCommand
    {
        $primaryKey = ['id' => Uuid::fromHexToBytes($documentId)];

        return new DeleteCommand(
            $definition,
            $primaryKey,
            new EntityExistence(
                DocumentDefinition::ENTITY_NAME,
                ['id' => $documentId],
                true,
                false,
                false,
                ['exists' => true, 'id' => $documentId]
            )
        );
    }
}
