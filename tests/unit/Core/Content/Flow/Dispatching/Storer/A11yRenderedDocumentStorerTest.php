<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Media\MediaEntity;
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

    public function testStoreWithCheckoutOrderPlacedEvent(): void
    {
        $documentId = Uuid::randomHex();
        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $event->method('getA11yDocumentIds')->willReturn([$documentId]);

        $stored = $this->storer->store($event, []);

        static::assertSame([$documentId], $stored[A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS]);
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

    #[DataProvider('documentSourceProvider')]
    public function testLazyLoadEntity(bool $configuredDocumentTypes): void
    {
        $a11yDocument = new MediaEntity();
        $a11yDocument->setId('id');
        $a11yDocument->setFileExtension('html');

        $documentWithA11yMediaFile = new DocumentEntity();
        $documentWithA11yMediaFile->setId('id');
        $documentWithA11yMediaFile->setDeepLinkCode('code1');
        $documentWithA11yMediaFile->setDocumentA11yMediaFile($a11yDocument);

        $documentWithNoA11yMediaFile = new DocumentEntity();
        $documentWithNoA11yMediaFile->setId('id2');
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

        $this->storer = new A11yRenderedDocumentStorer($this->repository, $this->dispatcher, $this->mailAttachmentsBuilder);
        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => $configuredDocumentTypes ? [] : ['id', 'id2']]);

        if ($configuredDocumentTypes) {
            $orderId = Uuid::randomHex();
            $documentTypeId = Uuid::randomHex();
            $storable->setData(OrderAware::ORDER_ID, $orderId);
            $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

            $this->mailAttachmentsBuilder
                ->expects($this->once())
                ->method('getLatestDocumentsOfTypes')
                ->with($orderId, [$documentTypeId])
                ->willReturn(['id', 'id2']);
        } else {
            $this->mailAttachmentsBuilder
                ->expects($this->never())
                ->method('getLatestDocumentsOfTypes');
        }

        $this->storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(1, $res);
        static::assertIsArray($res[0]);
        static::assertArrayHasKey('documentId', $res[0]);
        static::assertArrayHasKey('deepLinkCode', $res[0]);
        static::assertArrayHasKey('fileExtension', $res[0]);
        static::assertEquals('id', $res[0]['documentId']);
        static::assertEquals('code1', $res[0]['deepLinkCode']);
        static::assertEquals('html', $res[0]['fileExtension']);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function documentSourceProvider(): iterable
    {
        yield 'stored document IDs' => [false];
        yield 'configured document types' => [true];
    }

    public function testLazyLoadNoDocumentsOfConfiguredTypes(): void
    {
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setData(OrderAware::ORDER_ID, $orderId);
        $storable->setConfig(['documentTypeIds' => [$documentTypeId]]);

        $this->mailAttachmentsBuilder
            ->expects($this->once())
            ->method('getLatestDocumentsOfTypes')
            ->with($orderId, [$documentTypeId])
            ->willReturn([]);
        $this->dispatcher->expects($this->never())->method('dispatch');

        $this->storer->restore($storable);

        static::assertSame([], $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS));
    }

    public function testLazyLoadConfiguredTypesWithoutOrderId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $storable->setConfig(['documentTypeIds' => [Uuid::randomHex()]]);

        $this->mailAttachmentsBuilder->expects($this->never())->method('getLatestDocumentsOfTypes');
        $this->dispatcher->expects($this->never())->method('dispatch');

        $this->storer->restore($storable);

        static::assertSame([], $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS));
    }

    public function testLazyLoadNoEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => []]);
        $this->storer->restore($storable);

        $res = $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);

        static::assertIsArray($res);
        static::assertCount(0, $res);
    }

    public function testDispatchBeforeLoadStorableFlowDataEvent(): void
    {
        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(BeforeLoadStorableFlowDataEvent::class),
                'flow.storer.document.criteria.event'
            );

        $storable = new StorableFlow('name', Context::createDefaultContext(), [A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS => ['id']]);
        $this->storer->restore($storable);
        $storable->getData(A11yRenderedDocumentAware::A11Y_DOCUMENTS);
    }
}
