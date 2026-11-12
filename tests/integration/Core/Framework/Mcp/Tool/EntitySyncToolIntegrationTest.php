<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Tool\EntitySyncTool;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class EntitySyncToolIntegrationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $product = (new ProductBuilder($this->ids, 'p1'))
            ->price(10.00)
            ->stock(5)
            ->categories(['keep', 'remove'])
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    public function testDryRunChangesNothing(): void
    {
        $result = $this->tool()($this->operations());

        static::assertNull($result->error, (string) $result->error?->message);
        static::assertTrue($result->meta['dryRun']);
        static::assertIsArray($result->data);
        static::assertContains($this->ids->get('new-tax'), $result->data['written']['tax']);
        static::assertSame(
            [['productId' => $this->ids->get('p1'), 'categoryId' => $this->ids->get('remove')]],
            $result->data['deleted']['product_category'],
        );

        static::assertNull($this->loadTax($this->ids->get('new-tax')), 'A dry run must not create the tax');
        $product = $this->loadProduct();
        static::assertSame(5, $product['stock'], 'A dry run must not update the product');
        static::assertEqualsCanonicalizing([$this->ids->get('keep'), $this->ids->get('remove')], $product['categoryIds'], 'A dry run must not remove the link');
    }

    public function testRealRunWrites(): void
    {
        $result = $this->tool()($this->operations(), false);

        static::assertNull($result->error, (string) $result->error?->message);
        static::assertFalse($result->meta['dryRun']);

        static::assertSame('Sync tax', $this->loadTax($this->ids->get('new-tax')));
        $product = $this->loadProduct();
        static::assertSame(42, $product['stock']);
        static::assertSame([$this->ids->get('keep')], $product['categoryIds']);
        static::assertTrue($this->categoryExists($this->ids->get('remove')), 'Removing the link must keep the category');
    }

    public function testFailedOperationWritesNothing(): void
    {
        $operations = json_encode([
            ['entity' => 'tax', 'action' => 'upsert', 'payload' => [['id' => $this->ids->get('new-tax'), 'name' => 'Sync tax', 'taxRate' => 7]]],
            ['key' => 'broken', 'entity' => 'product', 'action' => 'upsert', 'payload' => [['id' => $this->ids->get('p1'), 'stock' => 'many']]],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->tool()($operations, false);

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
        static::assertStringContainsString('/broken/0/stock', $result->error->message);
        static::assertNull($this->loadTax($this->ids->get('new-tax')), 'The sync is one transaction');
    }

    public function testDalReportsNestedPrivilegesInADryRun(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:update']);
        $context = new Context($source);

        $operations = json_encode([[
            'entity' => 'product',
            'action' => 'upsert',
            'payload' => [['id' => $this->ids->get('p1'), 'categories' => [['id' => $this->ids->get('new-category'), 'name' => 'New']]]],
        ]], \JSON_THROW_ON_ERROR);

        $result = $this->tool($context)($operations);

        static::assertSame(McpToolError::MISSING_PRIVILEGE, $result->error?->code);
        static::assertStringContainsString('category:create', $result->error->message);
        static::assertFalse($this->categoryExists($this->ids->get('new-category')));
    }

    private function operations(): string
    {
        return json_encode([
            ['key' => 'tax', 'entity' => 'tax', 'action' => 'upsert', 'payload' => [['id' => $this->ids->get('new-tax'), 'name' => 'Sync tax', 'taxRate' => 7]]],
            ['key' => 'stock', 'entity' => 'product', 'action' => 'upsert', 'payload' => [['id' => $this->ids->get('p1'), 'stock' => 42]]],
            ['key' => 'unlink', 'entity' => 'product_category', 'action' => 'delete', 'payload' => [['productId' => $this->ids->get('p1'), 'categoryId' => $this->ids->get('remove')]]],
        ], \JSON_THROW_ON_ERROR);
    }

    private function tool(?Context $context = null): EntitySyncTool
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context ?? Context::createDefaultContext());

        return new EntitySyncTool(
            static::getContainer()->get(SyncService::class),
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            $contextProvider,
            static::getContainer()->get(Connection::class),
        );
    }

    private function loadTax(string $id): ?string
    {
        /** @var EntityRepository<TaxCollection> $repository */
        $repository = static::getContainer()->get('tax.repository');

        return $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->get($id)?->getName();
    }

    /**
     * @return array{stock: int, categoryIds: list<string>}
     */
    private function loadProduct(): array
    {
        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $criteria = (new Criteria([$this->ids->get('p1')]))->addAssociation('categories');

        $product = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($product);

        return [
            'stock' => $product->getStock(),
            'categoryIds' => array_values($product->getCategories()?->getIds() ?? []),
        ];
    }

    private function categoryExists(string $id): bool
    {
        return (bool) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT 1 FROM category WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );
    }
}
