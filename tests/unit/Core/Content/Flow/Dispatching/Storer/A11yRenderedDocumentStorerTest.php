<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Shared\MailFlow\DocumentResolver;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(A11yRenderedDocumentStorer::class)]
class A11yRenderedDocumentStorerTest extends TestCase
{
    private A11yRenderedDocumentStorer $storer;

    /**
     * @var StaticEntityRepository<DocumentCollection>
     */
    private StaticEntityRepository $repository;

    private EventDispatcherInterface&Stub $dispatcher;

    protected function setUp(): void
    {
        $this->repository = StaticEntityRepository::of(DocumentCollection::class, [[]]);
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);
        $this->storer = new A11yRenderedDocumentStorer($this->repository, $this->dispatcher, $this->createResolver(), new DocumentFileResolver());
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(OrderStateMachineStateChangeEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(UserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => ['id']]);

        $this->storer->restore($storable);

        static::assertArrayHasKey(A11yRenderedDocumentAware::A11Y_DOCUMENTS, $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $documentId = Uuid::randomHex();
        $documentId2 = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();

        $a11yDocument = new MediaEntity();
        $a11yDocument->setId(Uuid::randomHex());
        $a11yDocument->setFileExtension('html');

        $documentWithA11yMediaFile = new DocumentEntity();
        $documentWithA11yMediaFile->setConfig([]);
        $documentWithA11yMediaFile->setId($documentId);
        $documentWithA11yMediaFile->setDeepLinkCode('code1');
        $documentWithA11yMediaFile->setDocumentA11yMediaFile($a11yDocument);

        $documentWithNoA11yMediaFile = new DocumentEntity();
        $documentWithNoA11yMediaFile->setConfig([]);
        $documentWithNoA11yMediaFile->setId($documentId2);
        $documentWithNoA11yMediaFile->setDeepLinkCode('code2');

        $documentCollections = new DocumentCollection();
        $documentCollections->add($documentWithA11yMediaFile);
        $documentCollections->add($documentWithNoA11yMediaFile);

        $repository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                2,
                $documentCollections,
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $storer = new A11yRenderedDocumentStorer($repository, $this->dispatcher, $this->createResolver([$documentId, $documentId2]), new DocumentFileResolver());

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(1, $res);
        static::assertIsArray($res[0]);
        static::assertArrayHasKey('documentId', $res[0]);
        static::assertArrayHasKey('deepLinkCode', $res[0]);
        static::assertArrayHasKey('fileExtension', $res[0]);
        static::assertSame($documentId, $res[0]['documentId']);
        static::assertSame('code1', $res[0]['deepLinkCode']);
        static::assertSame('html', $res[0]['fileExtension']);
    }

    public function testLazyLoadNoDocumentTypeIds(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setConfig([]);

        $this->storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(0, $res);
    }

    public function testLazyLoadNoOrderId(): void
    {
        $documentTypeId = Uuid::randomHex();

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $this->storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(0, $res);
    }

    public function testLazyLoadNoDocumentsFound(): void
    {
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();

        $storer = new A11yRenderedDocumentStorer($this->repository, $this->dispatcher, $this->createResolver(), new DocumentFileResolver());

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(0, $res);
    }

    public function testDispatchBeforeLoadStorableFlowDataEvent(): void
    {
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(static fn (MailFlowDataCriteriaEvent $event): bool => $event->criteria->getAssociation('documentFiles')->hasAssociation('media')),
                'mail-flow.data.document.criteria.event'
            );

        $storer = new A11yRenderedDocumentStorer($this->repository, $dispatcher, $this->createResolver([$documentId]), new DocumentFileResolver());

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $storer->restore($storable);
        $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);
    }

    public function testLazyLoadV2ConfigUnionsExplicitlyPassedDocumentIds(): void
    {
        $explicitId = Uuid::randomHex();
        $resolvedId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $documents = new DocumentCollection();
        foreach ([$explicitId => 'code-explicit', $resolvedId => 'code-resolved'] as $id => $deepLinkCode) {
            $a11yMedia = new MediaEntity();
            $a11yMedia->setId(Uuid::randomHex());
            $a11yMedia->setFileExtension('html');

            $document = new DocumentEntity();
            $document->setConfig([]);
            $document->setId($id);
            $document->setDeepLinkCode($deepLinkCode);
            $document->setDocumentA11yMediaFile($a11yMedia);

            $documents->add($document);
        }

        $repository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                2,
                $documents,
                null,
                new Criteria(),
                $context,
            ),
        ]);

        $storer = new A11yRenderedDocumentStorer($repository, $this->dispatcher, $this->createResolver(latestByTechnicalName: $resolvedId), new DocumentFileResolver());

        $storable = new StorableFlow('name', $context, [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => [$explicitId]]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentType' => 'invoice', 'fileFormats' => ['pdf']]);

        $storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertSame([$explicitId, $resolvedId], array_column($res, 'documentId'));
    }

    public function testLazyLoadV2ConfigMapsHtmlDocumentFile(): void
    {
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $htmlMedia = new MediaEntity();
        $htmlMedia->setId(Uuid::randomHex());
        $htmlMedia->setFileExtension('html');

        $htmlFile = new DocumentFileEntity();
        $htmlFile->setId(Uuid::randomHex());
        $htmlFile->setDocumentId($documentId);
        $htmlFile->setMediaId($htmlMedia->getId());
        $htmlFile->setDocumentFormat('html');
        $htmlFile->setMedia($htmlMedia);

        $document = new DocumentEntity();
        $document->setConfig([]);
        $document->setId($documentId);
        $document->setDeepLinkCode('code1');
        $document->setDocumentFiles(new DocumentFileCollection([$htmlFile]));

        $repository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                $context,
            ),
        ]);

        $storer = new A11yRenderedDocumentStorer($repository, $this->dispatcher, $this->createResolver(latestByTechnicalName: $documentId), new DocumentFileResolver());

        $storable = new StorableFlow('name', $context, [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentType' => 'invoice', 'fileFormats' => ['pdf', 'html']]);

        $storer->restore($storable);

        static::assertSame(
            [
                [
                    'documentId' => $documentId,
                    'deepLinkCode' => 'code1',
                    'fileExtension' => 'html',
                ],
            ],
            $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS)
        );
    }

    public function testLazyLoadV2ConfigWithBothA11ySlotAndHtmlDocumentFileYieldsOneEntry(): void
    {
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $a11yMedia = new MediaEntity();
        $a11yMedia->setId(Uuid::randomHex());
        $a11yMedia->setFileExtension('html');

        $htmlMedia = new MediaEntity();
        $htmlMedia->setId(Uuid::randomHex());

        $htmlFile = new DocumentFileEntity();
        $htmlFile->setId(Uuid::randomHex());
        $htmlFile->setDocumentId($documentId);
        $htmlFile->setMediaId($htmlMedia->getId());
        $htmlFile->setDocumentFormat('html');
        $htmlFile->setMedia($htmlMedia);

        $document = new DocumentEntity();
        $document->setConfig([]);
        $document->setId($documentId);
        $document->setDeepLinkCode('code1');
        $document->setDocumentA11yMediaFile($a11yMedia);
        $document->setDocumentFiles(new DocumentFileCollection([$htmlFile]));

        $repository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                $context,
            ),
        ]);

        $storer = new A11yRenderedDocumentStorer($repository, $this->dispatcher, $this->createResolver(latestByTechnicalName: $documentId), new DocumentFileResolver());

        $storable = new StorableFlow('name', $context, [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentType' => 'invoice', 'fileFormats' => ['html']]);

        $storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertCount(1, $res);
        static::assertSame($documentId, $res[0]['documentId']);
        static::assertSame('html', $res[0]['fileExtension']);
    }

    public function testLazyLoadV2ConfigDocumentWithoutHtmlFileYieldsNoEntry(): void
    {
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());
        $pdfMedia->setFileExtension('pdf');

        $pdfFile = new DocumentFileEntity();
        $pdfFile->setId(Uuid::randomHex());
        $pdfFile->setDocumentId($documentId);
        $pdfFile->setMediaId($pdfMedia->getId());
        $pdfFile->setDocumentFormat('pdf');
        $pdfFile->setMedia($pdfMedia);

        $document = new DocumentEntity();
        $document->setConfig([]);
        $document->setId($documentId);
        $document->setDeepLinkCode('code1');
        $document->setDocumentFiles(new DocumentFileCollection([$pdfFile]));

        $repository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                $context,
            ),
        ]);

        $storer = new A11yRenderedDocumentStorer($repository, $this->dispatcher, $this->createResolver(latestByTechnicalName: $documentId), new DocumentFileResolver());

        $storable = new StorableFlow('name', $context, [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentType' => 'invoice', 'fileFormats' => ['pdf']]);

        $storer->restore($storable);

        static::assertSame([], $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS));
    }

    public function testLazyLoadV2ConfigNoDocumentFound(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $storer = new A11yRenderedDocumentStorer(
            StaticEntityRepository::of(DocumentCollection::class, []),
            $this->dispatcher,
            $this->createResolver(),
            new DocumentFileResolver()
        );

        $storable = new StorableFlow('name', $context, [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentType' => 'invoice', 'fileFormats' => ['pdf']]);

        $storer->restore($storable);

        static::assertSame([], $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS));
    }

    public function testLazyLoadFallbackToStoredIds(): void
    {
        $documentId = Uuid::randomHex();

        $a11yDocument = new MediaEntity();
        $a11yDocument->setId(Uuid::randomHex());
        $a11yDocument->setFileExtension('html');

        $document = new DocumentEntity();
        $document->setConfig([]);
        $document->setId($documentId);
        $document->setDeepLinkCode('code1');
        $document->setDocumentA11yMediaFile($a11yDocument);

        $repository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
        ]);

        $storer = new A11yRenderedDocumentStorer($repository, $this->dispatcher, $this->createResolver(), new DocumentFileResolver());

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => [$documentId]]);
        $storable->setConfig([]);

        $storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertCount(1, $res);
        static::assertSame($documentId, $res[0]['documentId']);
    }

    /**
     * The storer only needs the resolved document ids; argument mapping is covered by DocumentResolverTest.
     *
     * @param array<string> $latestOfTypes ids the v1 `documentTypeIds` lookup resolves to
     */
    private function createResolver(array $latestOfTypes = [], ?string $latestByTechnicalName = null): DocumentResolver
    {
        $context = Context::createDefaultContext();

        // one document per type, so each one is the latest of its own type
        $documents = new DocumentCollection(array_map(static function (string $id): DocumentEntity {
            $document = new DocumentEntity();
            $document->setId($id);
            $document->setDocumentTypeId(Uuid::randomHex());

            return $document;
        }, $latestOfTypes));

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(DocumentDefinition::ENTITY_NAME, $documents->count(), $documents, null, new Criteria(), $context)
        );
        $documentRepository->method('searchIds')->willReturn(IdSearchResult::fromIds(
            $latestByTechnicalName === null ? [] : [$latestByTechnicalName],
            new Criteria(),
            $context,
        ));

        return new DocumentResolver($documentRepository);
    }
}
