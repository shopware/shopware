<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

    private MockObject&EventDispatcherInterface $dispatcher;

    private MockObject&MailAttachmentsBuilder $mailAttachmentsBuilder;

    protected function setUp(): void
    {
        $this->repository = new StaticEntityRepository([[]]);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mailAttachmentsBuilder = $this->createMock(MailAttachmentsBuilder::class);
        $this->storer = new A11yRenderedDocumentStorer($this->repository, $this->dispatcher, $this->mailAttachmentsBuilder);
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(OrderStateMachineStateChangeEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(UserRecoveryRequestEvent::class);
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
        $documentWithA11yMediaFile->setId($documentId);
        $documentWithA11yMediaFile->setDeepLinkCode('code1');
        $documentWithA11yMediaFile->setDocumentA11yMediaFile($a11yDocument);

        $documentWithNoA11yMediaFile = new DocumentEntity();
        $documentWithNoA11yMediaFile->setId($documentId2);
        $documentWithNoA11yMediaFile->setDeepLinkCode('code2');

        $documentCollections = new DocumentCollection();
        $documentCollections->add($documentWithA11yMediaFile);
        $documentCollections->add($documentWithNoA11yMediaFile);

        $this->repository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                2,
                $documentCollections,
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->mailAttachmentsBuilder
            ->expects($this->once())
            ->method('getLatestDocumentsOfTypes')
            ->with($orderId, [$documentTypeId])
            ->willReturn([$documentId, $documentId2]);

        $this->storer = new A11yRenderedDocumentStorer($this->repository, $this->dispatcher, $this->mailAttachmentsBuilder);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $this->storer->restore($storable);

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

        $this->mailAttachmentsBuilder
            ->expects($this->once())
            ->method('getLatestDocumentsOfTypes')
            ->with($orderId, [$documentTypeId])
            ->willReturn([]);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $this->storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(0, $res);
    }

    public function testDispatchBeforeLoadStorableFlowDataEvent(): void
    {
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->mailAttachmentsBuilder
            ->expects($this->once())
            ->method('getLatestDocumentsOfTypes')
            ->willReturn([$documentId]);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(MailFlowDataCriteriaEvent::class),
                'mail-flow.data.document.criteria.event'
            );

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $this->storer->restore($storable);
        $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);
    }

    public function testLazyLoadFallbackToStoredIds(): void
    {
        $documentId = Uuid::randomHex();

        $a11yDocument = new MediaEntity();
        $a11yDocument->setId(Uuid::randomHex());
        $a11yDocument->setFileExtension('html');

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDeepLinkCode('code1');
        $document->setDocumentA11yMediaFile($a11yDocument);

        $this->repository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
        ]);

        $this->mailAttachmentsBuilder
            ->expects($this->never())
            ->method('getLatestDocumentsOfTypes');

        $this->storer = new A11yRenderedDocumentStorer($this->repository, $this->dispatcher, $this->mailAttachmentsBuilder);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => [$documentId]]);
        $storable->setConfig([]);

        $this->storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertCount(1, $res);
        static::assertSame($documentId, $res[0]['documentId']);
    }
}
