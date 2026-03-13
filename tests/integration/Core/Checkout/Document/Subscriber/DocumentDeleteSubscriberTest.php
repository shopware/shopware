<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentDeleteSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use DocumentTrait;
    use KernelTestBehaviour;

    private Context $context;

    private SalesChannelContext $salesChannelContext;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    private Connection $connection;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );

        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testDeleteDocumentShouldDeleteDependingMediaEntities(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $documentGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($documentGenerationResult);

        $documentId = $documentGenerationResult->getId();
        $mediaId = $documentGenerationResult->getMediaId();
        static::assertNotNull($mediaId);
        $a11yMediaId = $documentGenerationResult->getA11yMediaId();
        static::assertNotNull($a11yMediaId);

        static::assertTrue($this->hasMediaEntity($mediaId));
        static::assertTrue($this->hasMediaEntity($a11yMediaId));

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $documentDeleteEventDispatched = false;
        $mediaDeleteEventDispatched = false;
        $this->addEventListener(
            $dispatcher,
            EntityDeleteEvent::class,
            function (EntityDeleteEvent $event) use (
                $documentId,
                $mediaId,
                $a11yMediaId,
                &$documentDeleteEventDispatched,
                &$mediaDeleteEventDispatched
            ): void {
                $documentIds = $event->getIds(DocumentDefinition::ENTITY_NAME);
                $mediaIds = $event->getIds(MediaDefinition::ENTITY_NAME);

                if ($documentIds !== []) {
                    static::assertContains($documentId, $documentIds);
                    $documentDeleteEventDispatched = true;
                }

                if ($mediaIds !== []) {
                    static::assertContains($mediaId, $mediaIds);
                    static::assertContains($a11yMediaId, $mediaIds);

                    $mediaDeleteEventDispatched = true;
                }
            }
        );

        $this->documentRepository->delete([['id' => $documentGenerationResult->getId()]], $this->context);

        static::assertTrue(
            $documentDeleteEventDispatched,
            'DocumentDeleteSubscriber should be triggered to delete media entities.'
        );

        static::assertTrue(
            $mediaDeleteEventDispatched,
            'MediaDeletionSubscriber should be triggered and delete media files.'
        );

        static::assertFalse($this->hasMediaEntity($mediaId), 'Media entity should be deleted when document is deleted.');
        static::assertFalse($this->hasMediaEntity($a11yMediaId), 'Media entity should be deleted when document is deleted.');
    }

    public function testDeleteDocumentWhichDependsOnOtherDocumentShouldThrowException(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoiceGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($invoiceGenerationResult);

        $invoiceDocumentId = $invoiceGenerationResult->getId();

        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult);

        $creditNoteDocumentNumber = $this->connection->fetchOne(
            'SELECT document_number FROM document WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($creditNoteDocumentGenerationResult->getId())],
        );

        static::expectExceptionObject(DocumentException::documentHasDependentDocuments(
            [
                \sprintf(
                    '%s %s (%s)',
                    CreditNoteRenderer::TYPE,
                    $creditNoteDocumentNumber ?? 'unknown',
                    $creditNoteDocumentGenerationResult->getId()
                ),
            ]
        ));

        $this->documentRepository->delete([['id' => $invoiceDocumentId]], $this->context);
    }

    public function testDeleteDocumentsInBulkShouldThrowExceptionAndReturnJustDependedDocumentsWhichAreNotRequestedForDeletion(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoiceGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($invoiceGenerationResult);

        $invoiceDocumentId = $invoiceGenerationResult->getId();

        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult);

        $creditNoteDocumentId = $creditNoteDocumentGenerationResult->getId();

        /*
         * add second credit item and credit note to have more than one dependent document
         */
        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult2 = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult2);

        $creditNoteDocumentId2 = $creditNoteDocumentGenerationResult2->getId();

        $data = [
            [
                'key' => 'test',
                'action' => SyncController::ACTION_DELETE,
                'entity' => static::getContainer()->get(DocumentDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $creditNoteDocumentId,
                    ],
                    [
                        'id' => $invoiceDocumentId,
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request(
            Request::METHOD_POST,
            '/api/_action/sync',
            [],
            [],
            [],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $content);
        static::assertCount(1, $content['errors']);
        $errorDetail = $content['errors'][0]['detail'];
        static::assertStringContainsString(': credit_note', $errorDetail);
        static::assertStringContainsString(\sprintf(' (%s).', $creditNoteDocumentId2), $errorDetail);
        static::assertStringNotContainsString(\sprintf(' (%s).', $creditNoteDocumentId), $errorDetail);
    }

    #[TestWith(['dependingDocumentFirst' => true], 'first depending document (credit note), second parent document (invoice)')]
    #[TestWith(['dependingDocumentFirst' => false], 'first parent document (invoice), second depending document (credit note)')]
    public function testDeleteDocumentsInBulkShouldNotThrowExceptionWhenDependentDocumentIsInRequestList(
        bool $dependingDocumentFirst
    ): void {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoiceGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($invoiceGenerationResult);

        $invoiceDocumentId = $invoiceGenerationResult->getId();
        $invoiceMediaId = $invoiceGenerationResult->getMediaId();
        static::assertNotNull($invoiceMediaId);
        $invoiceA11yMediaId = $invoiceGenerationResult->getA11yMediaId();
        static::assertNotNull($invoiceA11yMediaId);

        static::assertTrue($this->hasMediaEntity($invoiceMediaId));
        static::assertTrue($this->hasMediaEntity($invoiceA11yMediaId));

        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult);

        $creditNoteDocumentId = $creditNoteDocumentGenerationResult->getId();
        $creditNoteMediaId = $creditNoteDocumentGenerationResult->getMediaId();
        static::assertNotNull($creditNoteMediaId);
        $creditNoteA11yMediaId = $creditNoteDocumentGenerationResult->getA11yMediaId();
        static::assertNotNull($creditNoteA11yMediaId);

        static::assertTrue($this->hasMediaEntity($creditNoteMediaId));
        static::assertTrue($this->hasMediaEntity($creditNoteA11yMediaId));

        $payload = $dependingDocumentFirst
            ? [['id' => $creditNoteDocumentId], ['id' => $invoiceDocumentId]]
            : [['id' => $invoiceDocumentId], ['id' => $creditNoteDocumentId]];

        $data = [
            [
                'key' => 'test',
                'action' => SyncController::ACTION_DELETE,
                'entity' => static::getContainer()->get(DocumentDefinition::class)->getEntityName(),
                'payload' => $payload,
            ],
        ];

        $this->getBrowser()->request(
            Request::METHOD_POST,
            '/api/_action/sync',
            [],
            [],
            [],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $documents = $content['deleted']['document'];
        static::assertCount(2, $documents);
        foreach ($documents as $document) {
            static::assertContains($document, [$invoiceDocumentId, $creditNoteDocumentId]);
        }
    }

    private function addCreditItemToOrder(string $orderId): void
    {
        $this->orderRepository->upsert(
            [[
                'id' => $orderId,
                'lineItems' => [
                    [
                        'id' => Uuid::randomHex(),
                        'identifier' => Uuid::randomHex(),
                        'quantity' => 1,
                        'label' => 'label',
                        'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
            ]],
            Context::createDefaultContext()
        );
    }

    private function hasMediaEntity(string $mediaId): bool
    {
        return (bool) $this->connection->fetchAssociative(
            'SELECT * FROM media WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($mediaId),
            ],
        );
    }
}
