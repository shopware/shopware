<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create('manual'),
            'fileName' => 'manual',
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
            'fileSize' => 1024,
        ]], Context::createDefaultContext());
    }

    public function testDocumentRoutesOfTheAdminApiInvalidateTheProductAndItsVariants(): void
    {
        $this->createDrillWithBlueVariant();
        $document = ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), ...$this->manual()];

        $created = $this->collectInvalidatedProductIds('POST', '/api/product-document', $document);
        static::assertContains($this->ids->get('drill'), $created);
        static::assertContains($this->ids->get('drill.blue'), $created);

        $updated = $this->collectInvalidatedProductIds('PATCH', '/api/product-document/' . $this->ids->get('document'), ['title' => 'Manual v2']);
        static::assertContains($this->ids->get('drill'), $updated);
        static::assertContains($this->ids->get('drill.blue'), $updated);

        $translated = $this->collectInvalidatedProductIds(
            'PATCH',
            '/api/product-document/' . $this->ids->get('document'),
            ['title' => 'Handbuch'],
            ['HTTP_SW_LANGUAGE_ID' => $this->getDeDeLanguageId()],
        );
        static::assertContains($this->ids->get('drill'), $translated);
        static::assertContains($this->ids->get('drill.blue'), $translated);

        $deleted = $this->collectInvalidatedProductIds('DELETE', '/api/product-document/' . $this->ids->get('document'));
        static::assertContains($this->ids->get('drill'), $deleted);
        static::assertContains($this->ids->get('drill.blue'), $deleted);
    }

    public function testSyncApiWritesOfDocumentsInvalidateTheProductAndItsVariants(): void
    {
        $this->createDrillWithBlueVariant();
        $document = ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), ...$this->manual()];

        $upserted = $this->collectInvalidatedProductIds('POST', '/api/_action/sync', [[
            'key' => 'documents',
            'action' => SyncController::ACTION_UPSERT,
            'entity' => ProductDocumentDefinition::ENTITY_NAME,
            'payload' => [$document],
        ]]);
        static::assertContains($this->ids->get('drill'), $upserted);
        static::assertContains($this->ids->get('drill.blue'), $upserted);

        $deleted = $this->collectInvalidatedProductIds('POST', '/api/_action/sync', [[
            'key' => 'documents',
            'action' => SyncController::ACTION_DELETE,
            'entity' => ProductDocumentDefinition::ENTITY_NAME,
            'payload' => [['id' => $this->ids->get('document')]],
        ]]);
        static::assertContains($this->ids->get('drill'), $deleted);
        static::assertContains($this->ids->get('drill.blue'), $deleted);
    }

    /**
     * @return array{title: string, mediaId: string}
     */
    private function manual(): array
    {
        return ['title' => 'Manual', 'mediaId' => $this->ids->get('manual')];
    }

    private function createDrillWithBlueVariant(): void
    {
        $product = (new ProductBuilder($this->ids, 'drill'))
            ->price(10)
            ->variant((new ProductBuilder($this->ids, 'drill.blue'))->build())
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    /**
     * @param array<mixed> $body
     * @param array<string, string> $server
     *
     * @return list<string>
     */
    private function collectInvalidatedProductIds(string $method, string $url, array $body = [], array $server = []): array
    {
        $invalidatedIds = [];
        $hook = static function (InvalidateProductCache $event) use (&$invalidatedIds): void {
            array_push($invalidatedIds, ...$event->getIds());
        };

        $dispatcher = EventHookDispatcher::fromContainer(static::getContainer());
        $dispatcher->on(InvalidateProductCache::class, $hook);
        $this->getBrowser()->request($method, $url, server: ['CONTENT_TYPE' => 'application/json', ...$server], content: json_encode($body, \JSON_THROW_ON_ERROR));
        $dispatcher->off(InvalidateProductCache::class, $hook);

        $response = $this->getBrowser()->getResponse();
        static::assertTrue($response->isSuccessful(), $method . ' ' . $url . ': ' . $response->getContent());

        return $invalidatedIds;
    }
}
