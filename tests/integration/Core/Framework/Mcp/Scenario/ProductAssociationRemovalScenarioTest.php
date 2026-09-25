<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Removing a category or a property option from a product means deleting the row of the
 * mapping entity, not the category or option itself (#20136).
 *
 * @internal
 */
#[Package('framework')]
class ProductAssociationRemovalScenarioTest extends McpScenarioTestCase
{
    private EntityDeleteTool $entityDeleteTool;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $this->entityDeleteTool = new EntityDeleteTool(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            $contextProvider,
            static::getContainer()->get(Connection::class),
        );

        $this->ids = new IdsCollection();
        $product = (new ProductBuilder($this->ids, 'p1'))
            ->price(10.00)
            ->categories(['keep', 'remove'])
            ->property('red', 'color')
            ->property('blue', 'color')
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    public function testSchemaNamesTheMappingEntityOfManyToManyAssociations(): void
    {
        $data = $this->decodeToolOutput(($this->entitySchemaTool)('product'));
        $associations = array_column($data['data']['associations'], null, 'name');

        static::assertSame('category', $associations['categories']['entity']);
        static::assertSame('product_category', $associations['categories']['mappingEntity']);
        static::assertSame('property_group_option', $associations['properties']['entity']);
        static::assertSame('product_property', $associations['properties']['mappingEntity']);
        static::assertArrayNotHasKey('mappingEntity', $associations['manufacturer']);
    }

    public function testRemovesCategoryFromProductWithoutDeletingTheCategory(): void
    {
        $ids = json_encode([[
            'productId' => $this->ids->get('p1'),
            'categoryId' => $this->ids->get('remove'),
        ]], \JSON_THROW_ON_ERROR);

        $this->decodeToolOutput(($this->entityDeleteTool)('product_category', $ids));
        static::assertEqualsCanonicalizing(
            [$this->ids->get('keep'), $this->ids->get('remove')],
            array_values($this->loadProduct()->getCategoryIds() ?? []),
            'A dry run must not remove the link',
        );

        $this->decodeToolOutput(($this->entityDeleteTool)('product_category', $ids, false));

        static::assertSame([$this->ids->get('keep')], $this->loadProduct()->getCategoryIds());
        static::assertSame(
            1,
            static::getContainer()->get('category.repository')->searchIds(new Criteria([$this->ids->get('remove')]), Context::createDefaultContext())->getTotal(),
            'The category itself must still exist',
        );
    }

    public function testRemovesPropertyOptionFromProduct(): void
    {
        $ids = json_encode([[
            'productId' => $this->ids->get('p1'),
            'optionId' => $this->ids->get('blue'),
        ]], \JSON_THROW_ON_ERROR);

        $this->decodeToolOutput(($this->entityDeleteTool)('product_property', $ids, false));

        static::assertSame([$this->ids->get('red')], $this->loadProduct()->getPropertyIds());
    }

    public function testExplainsCompositeKeyWhenPlainIdsArePassed(): void
    {
        $error = $this->decodeToolError(($this->entityDeleteTool)('product_category', json_encode([$this->ids->get('remove')], \JSON_THROW_ON_ERROR), false));

        static::assertStringContainsString('productId and categoryId', $error['error']);
        static::assertCount(2, $this->loadProduct()->getCategoryIds() ?? []);
    }

    private function loadProduct(): ProductEntity
    {
        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $product = $repository->search(new Criteria([$this->ids->get('p1')]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }
}
