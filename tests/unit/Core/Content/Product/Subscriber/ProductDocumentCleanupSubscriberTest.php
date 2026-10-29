<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentCleanupSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentCleanupSubscriber::class)]
class ProductDocumentCleanupSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    /**
     * @var StaticEntityRepository<ProductDocumentCollection>
     */
    private StaticEntityRepository $productDocumentRepository;

    private ProductDocumentCleanupSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProductDocumentDefinition::class, ProductDocumentTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );
        $this->productDocumentRepository = StaticEntityRepository::of(ProductDocumentCollection::class);
        $this->subscriber = new ProductDocumentCleanupSubscriber($this->productDocumentRepository);
    }

    public function testDeletesADocumentOnceTheDeleteOfItsDefaultTranslationSucceeded(): void
    {
        $documentId = Uuid::randomHex();
        $event = $this->deleteEvent($this->translationDeletion($documentId, Defaults::LANGUAGE_SYSTEM));

        $this->subscriber->deleteDocumentsWithoutDefaultTranslation($event);
        static::assertSame([], $this->productDocumentRepository->deletes);

        $event->success();
        static::assertSame([[['id' => $documentId, 'versionId' => Defaults::LIVE_VERSION]]], $this->productDocumentRepository->deletes);
    }

    public function testDeletesTheDocumentInTheVersionOfItsTranslation(): void
    {
        $documentId = Uuid::randomHex();
        $versionId = Uuid::randomHex();
        $event = $this->deleteEvent($this->translationDeletion($documentId, Defaults::LANGUAGE_SYSTEM, $versionId));

        $this->subscriber->deleteDocumentsWithoutDefaultTranslation($event);
        $event->success();

        static::assertSame([[['id' => $documentId, 'versionId' => $versionId]]], $this->productDocumentRepository->deletes);
    }

    public function testKeepsDocumentsThatOnlyLoseAnotherLanguage(): void
    {
        $event = $this->deleteEvent($this->translationDeletion(Uuid::randomHex(), Uuid::randomHex()));

        $this->subscriber->deleteDocumentsWithoutDefaultTranslation($event);
        $event->success();

        static::assertSame([], $this->productDocumentRepository->deletes);
    }

    public function testLeavesDocumentsThatAreDeletedAnywayToTheirOwnDelete(): void
    {
        $documentId = Uuid::randomHex();
        $event = $this->deleteEvent(
            $this->documentDeletion($documentId),
            $this->translationDeletion($documentId, Defaults::LANGUAGE_SYSTEM),
        );

        $this->subscriber->deleteDocumentsWithoutDefaultTranslation($event);
        $event->success();

        static::assertSame([], $this->productDocumentRepository->deletes);
    }

    private function deleteEvent(DeleteCommand ...$commands): EntityDeleteEvent
    {
        return EntityDeleteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
    }

    private function translationDeletion(string $documentId, string $languageId, string $versionId = Defaults::LIVE_VERSION): DeleteCommand
    {
        $primaryKey = [
            'product_document_id' => Uuid::fromHexToBytes($documentId),
            'product_document_version_id' => Uuid::fromHexToBytes($versionId),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ];

        return new DeleteCommand(
            $this->registry->getByEntityName(ProductDocumentTranslationDefinition::ENTITY_NAME),
            $primaryKey,
            new EntityExistence(ProductDocumentTranslationDefinition::ENTITY_NAME, $primaryKey, true, false, false, []),
        );
    }

    private function documentDeletion(string $documentId): DeleteCommand
    {
        $primaryKey = [
            'id' => Uuid::fromHexToBytes($documentId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ];

        return new DeleteCommand(
            $this->registry->getByEntityName(ProductDocumentDefinition::ENTITY_NAME),
            $primaryKey,
            new EntityExistence(ProductDocumentDefinition::ENTITY_NAME, $primaryKey, true, false, false, []),
        );
    }
}
