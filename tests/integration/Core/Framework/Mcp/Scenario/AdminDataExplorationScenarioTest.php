<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class AdminDataExplorationScenarioTest extends McpScenarioTestCase
{
    public function testUS2LowStockProducts(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $products = [
            (new ProductBuilder($ids, 'p-high'))->price(100)->stock(10)->build(),
            (new ProductBuilder($ids, 'p-low1'))->price(50)->stock(3)->build(),
            (new ProductBuilder($ids, 'p-low2'))->price(25)->stock(1)->build(),
        ];

        static::getContainer()->get('product.repository')->create($products, $context);

        $output = ($this->entitySearchTool)('product', json_encode([
            'filter' => [
                ['type' => 'range', 'field' => 'stock', 'parameters' => ['lt' => 5]],
                ['type' => 'multi', 'operator' => 'OR', 'queries' => [
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-low1')],
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-low2')],
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-high')],
                ]],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = $this->decodeToolOutput($output);

        static::assertSame(2, $data['_meta']['total']);
        foreach ($data['data'] as $product) {
            static::assertLessThan(5, $product['stock']);
        }
    }

    public function testSearchWithProductsAndAssociationsReturnsInlineResult(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $products = [];
        for ($i = 0; $i < 3; ++$i) {
            $products[] = (new ProductBuilder($ids, "prod-$i"))
                ->price(100)
                ->stock(10)
                ->manufacturer('manufacturer')
                ->property('red', 'color')
                ->property('XL', 'size')
                ->build();
        }

        static::getContainer()->get('product.repository')->create($products, $context);

        $criteria = json_encode([
            'ids' => array_map(fn (int $i) => $ids->get("prod-$i"), range(0, 2)),
            'associations' => [
                'properties' => ['associations' => ['group' => new \stdClass()]],
                'manufacturer' => new \stdClass(),
            ],
        ], \JSON_THROW_ON_ERROR);

        $output = ($this->entitySearchTool)('product', $criteria);
        $data = $this->decodeToolOutput($output);

        static::assertCount(3, $data['data']);
        static::assertSame(3, $data['_meta']['total']);
        static::assertArrayNotHasKey('truncated', $data['_meta']);
    }

    public function testUS3CustomerEntitySchema(): void
    {
        $output = ($this->entitySchemaTool)('customer');
        $data = $this->decodeToolOutput($output);

        $fieldNames = array_column($data['data']['fields'], 'name');
        static::assertContains('email', $fieldNames);
        static::assertContains('firstName', $fieldNames);
        static::assertContains('lastName', $fieldNames);
        static::assertContains('customerNumber', $fieldNames);

        $assocNames = array_column($data['data']['associations'], 'name');
        static::assertContains('orderCustomers', $assocNames);
        static::assertContains('addresses', $assocNames);
        static::assertContains('group', $assocNames);
    }
}
