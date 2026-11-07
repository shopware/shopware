<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('framework')]
class ManyToOneAssociationFieldResolverTest extends TestCase
{
    use DocumentTrait;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<OrderCollection>
     */
    protected EntityRepository $orderRepository;

    /**
     * @var EntityRepository<ProductCollection>
     */
    protected EntityRepository $productRepository;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    protected EntityRepository $documentRepository;

    protected Connection $connection;

    protected SalesChannelContext $salesChannelContext;

    protected Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $this->createCustomer()]
        );
    }

    public function testVersionedReferenceJoinsOnlyTheReferencedOrderVersion(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $document = $this->createDocumentEntity('invoice', $orderId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        // A second order version that the document does not reference
        $versionId = $this->orderRepository->createVersion($orderId, $this->context);
        $this->orderRepository->update(
            [['id' => $orderId, 'orderNumber' => 'unreferenced-version']],
            $this->context->createWithVersionId($versionId),
        );

        $documents = $this->documentRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('order.orderNumber', $order->getOrderNumber()))
                ->addAssociation('order'),
            $this->context,
        )->getEntities();

        static::assertSame([$document->getId()], array_values($documents->getIds()));
        static::assertSame($document->getOrderVersionId(), $documents->first()?->getOrder()?->getVersionId());

        $documentIds = $this->documentRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('order.orderNumber', 'unreferenced-version')),
            $this->context,
        )->getIds();

        static::assertSame([], $documentIds);
    }

    public function testReferenceToNonVersionedEntityJoinsWithoutVersion(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $document = $this->createDocumentEntity('invoice', $orderId);

        $documentIds = $this->documentRepository->searchIds(
            (new Criteria([$document->getId()]))->addFilter(new EqualsFilter('documentType.technicalName', 'invoice')),
            $this->context,
        )->getIds();

        static::assertSame([$document->getId()], $documentIds);
    }

    public function testReferenceToSelfJoinsWithoutVersion(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoice = $this->createDocumentEntity('invoice', $orderId);
        $deliveryNote = $this->createDocumentEntity('delivery_note', $orderId);

        $this->documentRepository->update(
            [['id' => $deliveryNote->getId(), 'referencedDocumentId' => $invoice->getId()]],
            $this->context,
        );

        $documentIds = $this->documentRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('referencedDocument.documentNumber', $invoice->getDocumentNumber())),
            $this->context,
        )->getIds();

        static::assertSame([$deliveryNote->getId()], $documentIds);
    }

    public function testOneToOneReferenceFromVersionedEntityJoinsTheSameVersion(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))->addAssociation('orderCustomer'),
            $this->context,
        )->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        $orderCustomer = $order->getOrderCustomer();
        static::assertNotNull($orderCustomer);

        $versionId = $this->orderRepository->createVersion($orderId, $this->context);
        $versionContext = $this->context->createWithVersionId($versionId);
        $this->orderRepository->update(
            [['id' => $orderId, 'orderCustomer' => ['id' => $orderCustomer->getId(), 'email' => 'version-only@example.com']]],
            $versionContext,
        );

        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderCustomer.email', 'version-only@example.com'));

        static::assertSame([], $this->orderRepository->searchIds($criteria, $this->context)->getIds());
        static::assertSame([$orderId], $this->orderRepository->searchIds($criteria, $versionContext)->getIds());
    }

    public function testCorrectOrderVersionOverAssociationOverRepositorySearch(): void
    {
        // 1. Create a new order and extract order number
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $order = $this->orderRepository->search(new Criteria([$orderId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        // 2. Generate a document attached to the order
        $this->createDocument('invoice', $orderId, [], $this->context);

        // 3. Set created order version to be lexicographically smaller than the live version
        $this->connection->executeStatement(
            'UPDATE `order`
            SET `version_id` = :newVersionId
            WHERE `version_id` != :liveVersionId AND `id` = :orderId',
            [
                'newVersionId' => hex2bin('00000000000000000000000000000000'),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'orderId' => hex2bin($orderId),
            ],
        );

        // 4. Search for the document over the order number of its attached order
        $documents = $this->documentRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('order.orderNumber', $order->getOrderNumber()))
                ->addAssociation('order')
                ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT),
            $this->context,
        );

        static::assertCount(1, $documents->getEntities());
        static::assertSame(1, $documents->getTotal());

        $document = $documents->getEntities()->first();
        static::assertInstanceOf(DocumentEntity::class, $document);
        static::assertNotNull($document->getOrder());
        static::assertSame('00000000000000000000000000000000', $document->getOrder()->getVersionId());
    }

    public function testManyToOneInheritedWorks(): void
    {
        $ids = new IdsCollection();
        $p = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->cover('cover')
            ->variant(
                (new ProductBuilder($ids, 'p2'))
                    ->price(200)
                    ->build()
            );

        $connection = static::getContainer()->get(Connection::class);

        $context = Context::createDefaultContext();
        $this->productRepository->create([$p->build()], $context);

        // Old database records don't have a product_media_version_id set
        $connection->executeStatement('UPDATE product SET product_media_version_id = NULL WHERE product_media_id IS NULL');

        $criteria = new Criteria([$ids->get('p1'), $ids->get('p2')]);
        $criteria->addAssociation('cover.media');

        $products = array_values($this->productRepository->search($criteria, $context)->getEntities()->getElements());

        static::assertCount(2, $products);

        [$product1, $product2] = $products;
        static::assertNotNull($product1->getCover());
        static::assertNull($product2->getCover());

        // Enable inheritance

        $context->setConsiderInheritance(true);

        $products = array_values($this->productRepository->search($criteria, $context)->getEntities()->getElements());

        static::assertCount(2, $products);

        [$product1, $product2] = $products;
        static::assertNotNull($product1->getCover());
        static::assertNotNull($product2->getCover());
    }

    private function createDocumentEntity(string $documentType, string $orderId): DocumentEntity
    {
        $documentId = $this->createDocument($documentType, $orderId, [], $this->context)->first()?->getId();
        static::assertNotNull($documentId);

        $document = $this->documentRepository->search(new Criteria([$documentId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(DocumentEntity::class, $document);

        return $document;
    }
}
