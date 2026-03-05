<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Subscriber;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentDeleteSubscriberTest extends TestCase
{
    use DocumentTrait;

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

        $mediaEntity = $this->connection->fetchOne(
            'SELECT 1 FROM media WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($mediaId)],
        );
        static::assertNotFalse($mediaEntity);

        $a11yMediaEntity = $this->connection->fetchOne(
            'SELECT 1 FROM media WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($a11yMediaId)],
        );
        static::assertNotFalse($a11yMediaEntity);

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

        $mediaEntity = $this->connection->fetchOne(
            'SELECT 1 FROM media WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($mediaId)],
        );
        static::assertFalse($mediaEntity, 'Media entity should be deleted when document is deleted.');

        $a11yMediaEntity = $this->connection->fetchOne(
            'SELECT 1 FROM media WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($a11yMediaId)],
        );
        static::assertFalse($a11yMediaEntity, 'Media entity should be deleted when document is deleted.');
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

        static::expectExceptionObject(DocumentException::documentHasDependencies());

        $this->documentRepository->delete([['id' => $invoiceDocumentId]], $this->context);
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
}
