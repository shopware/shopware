<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class ProductDescriptionTeaserIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHandleBackfillsMissingTeaser(): void
    {
        $ids = new IdsCollection();
        $this->createProduct($ids);

        $connection = static::getContainer()->get(Connection::class);

        // Simulate a row that existed before the column was added: the teaser is still missing.
        $connection->executeStatement(
            'UPDATE product_translation SET description_teaser = NULL WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('product'))]
        );

        $this->getIndexer()->handle(new EntityIndexingMessage([$ids->get('product')]));

        $teaser = $connection->fetchOne(
            'SELECT description_teaser FROM product_translation WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('product'))]
        );

        static::assertSame('Hello World', $teaser);
    }

    public function testHandleRewritesStaleTeaser(): void
    {
        $ids = new IdsCollection();
        $this->createProduct($ids);

        $connection = static::getContainer()->get(Connection::class);

        // A non-null but stale teaser (e.g. left behind by a raw-SQL write or a change to the
        // html_sanitizer rules) is rebuilt from the current description.
        $connection->executeStatement(
            'UPDATE product_translation SET description_teaser = :value WHERE product_id = :id',
            ['value' => 'stale value', 'id' => Uuid::fromHexToBytes($ids->get('product'))]
        );

        $this->getIndexer()->handle(new EntityIndexingMessage([$ids->get('product')]));

        $teaser = $connection->fetchOne(
            'SELECT description_teaser FROM product_translation WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('product'))]
        );

        static::assertSame('Hello World', $teaser);
    }

    public function testHandleLeavesUpToDateTeaserUntouched(): void
    {
        $ids = new IdsCollection();
        $this->createProduct($ids);

        $connection = static::getContainer()->get(Connection::class);

        // A teaser that already matches the current description is left as-is (no needless rewrite).
        $connection->executeStatement(
            'UPDATE product_translation SET description_teaser = :value WHERE product_id = :id',
            ['value' => 'Hello World', 'id' => Uuid::fromHexToBytes($ids->get('product'))]
        );

        $this->getIndexer()->handle(new EntityIndexingMessage([$ids->get('product')]));

        $teaser = $connection->fetchOne(
            'SELECT description_teaser FROM product_translation WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('product'))]
        );

        static::assertSame('Hello World', $teaser);
    }

    public function testUpdateReturnsNullToLeaveLiveWritesToSubscriber(): void
    {
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection(),
            []
        );

        static::assertNull($this->getIndexer()->update($event));
    }

    private function getIndexer(): ProductDescriptionTeaserIndexer
    {
        return static::getContainer()->get(ProductDescriptionTeaserIndexer::class);
    }

    private function createProduct(IdsCollection $ids): void
    {
        static::getContainer()->get('product.repository')->create([[
            'id' => $ids->create('product'),
            'productNumber' => $ids->get('product'),
            'name' => 'Teaser indexer probe',
            'description' => '<p style="color: red;">Hello <strong>World</strong></p>',
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'tax' => ['name' => 'probe', 'taxRate' => 19],
        ]], Context::createDefaultContext());
    }
}
