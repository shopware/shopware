<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Subscriber\ProductInheritanceSubscriber;

/**
 * @internal
 */
#[Package('inventory')]
class ProductAdminSearchIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();

        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create('file'),
            'fileName' => 'manual-' . $this->ids->get('file'),
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
            'fileSize' => 1024,
        ]], $this->context);

        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'drill'))
                ->price(10)
                ->variant((new ProductBuilder($this->ids, 'drill.blue'))->build())
                ->variant((new ProductBuilder($this->ids, 'drill.red'))->build())
                ->build(),
        ], $this->context);
    }

    public function testVariantsAreIndexedWithTheDocumentsTheyInherit(): void
    {
        $this->createDocument('manual', 'drill');
        $this->createDocument('own', 'drill.red');

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function (): void {
            $documents = $this->getIndexer()->fetch($this->ids->getList(['drill', 'drill.blue', 'drill.red']));

            static::assertSame([['id' => $this->ids->get('manual'), '_count' => 1]], $documents[$this->ids->get('drill')]['documents']);
            static::assertSame([['id' => $this->ids->get('manual'), '_count' => 1]], $documents[$this->ids->get('drill.blue')]['documents']);
            static::assertSame([['id' => $this->ids->get('own'), '_count' => 1]], $documents[$this->ids->get('drill.red')]['documents']);
        });
    }

    public function testAQueuedInheritanceUpdateIndexesTheVariantsAgain(): void
    {
        $this->createDocument('manual', 'drill');
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE product SET documents = NULL WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($this->ids->getList(['drill.blue', 'drill.red']))],
            ['ids' => ArrayParameterType::BINARY]
        );

        $indexedIds = [];
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('updateIds')->willReturnCallback(static function (string $entity, array $ids) use (&$indexedIds): void {
            static::assertSame('product', $entity);
            $indexedIds = $ids;
        });
        $listener = (new ProductInheritanceSubscriber($registry))->update(...);

        $dispatcher = EventHookDispatcher::fromContainer(static::getContainer());
        $dispatcher->on(ProductIndexerEvent::class, $listener);
        try {
            Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function (): void {
                static::getContainer()->get(ProductIndexer::class)->handle(new ProductIndexingMessage($this->ids->getList(['drill.blue', 'drill.red']), null, $this->context));
            });
        } finally {
            $dispatcher->off(ProductIndexerEvent::class, $listener);
        }

        static::assertEqualsCanonicalizing(array_values($this->ids->getList(['drill.blue', 'drill.red'])), array_values($indexedIds));

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function (): void {
            $documents = $this->getIndexer()->fetch($this->ids->getList(['drill.blue', 'drill.red']));

            static::assertSame([['id' => $this->ids->get('manual'), '_count' => 1]], $documents[$this->ids->get('drill.blue')]['documents']);
            static::assertSame([['id' => $this->ids->get('manual'), '_count' => 1]], $documents[$this->ids->get('drill.red')]['documents']);
        });
    }

    public function testAddingADocumentUpdatesTheProductAndItsVariants(): void
    {
        $event = $this->createDocument('manual', 'drill');

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($event): void {
            static::assertEqualsCanonicalizing(
                array_values($this->ids->getList(['drill', 'drill.blue', 'drill.red'])),
                $this->getIndexer()->getUpdatedIds($event)
            );
        });
    }

    public function testDeletingADocumentUpdatesTheProductAndItsVariants(): void
    {
        $this->createDocument('manual', 'drill');

        $event = static::getContainer()->get('product_document.repository')->delete([['id' => $this->ids->get('manual')]], $this->context);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($event): void {
            static::assertEqualsCanonicalizing(
                array_values($this->ids->getList(['drill', 'drill.blue', 'drill.red'])),
                $this->getIndexer()->getUpdatedIds($event)
            );
        });
    }

    public function testUpdatingTheTitleOfADocumentDoesNotUpdateTheProducts(): void
    {
        $this->createDocument('manual', 'drill');

        $event = static::getContainer()->get('product_document.repository')->update([
            ['id' => $this->ids->get('manual'), 'title' => 'Instructions', 'position' => 2],
        ], $this->context);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($event): void {
            static::assertSame([], $this->getIndexer()->getUpdatedIds($event));
        });
    }

    private function createDocument(string $key, string $productKey): EntityWrittenContainerEvent
    {
        return static::getContainer()->get('product_document.repository')->create([[
            'id' => $this->ids->create($key),
            'productId' => $this->ids->get($productKey),
            'title' => 'Manual',
            'mediaId' => $this->ids->get('file'),
        ]], $this->context);
    }

    private function getIndexer(): ProductAdminSearchIndexer
    {
        return static::getContainer()->get(ProductAdminSearchIndexer::class);
    }
}
