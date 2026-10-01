<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class ProductContentScenarioTest extends McpScenarioTestCase
{
    public function testUS22ProductsWithoutMedia(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $productWithMedia = (new ProductBuilder($ids, 'with-media'))
            ->price(10.00)
            ->media('test-media-id')
            ->build();

        $productWithoutMedia = (new ProductBuilder($ids, 'without-media'))
            ->price(10.00)
            ->build();

        static::getContainer()->get('product.repository')->create([$productWithMedia, $productWithoutMedia], $context);

        $output = ($this->entitySearchTool)(
            entity: 'product',
            criteria: json_encode([
                'filter' => [
                    ['type' => 'equals', 'field' => 'media.id', 'value' => null],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);

        $foundIds = array_column($data['data'], 'id');
        static::assertContains($ids->get('without-media'), $foundIds, 'Product without media should appear');
        static::assertNotContains($ids->get('with-media'), $foundIds, 'Product with media should not appear');
    }

    public function testUS23PriceUpdate(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $product = (new ProductBuilder($ids, 'price-update'))
            ->price(19.99)
            ->build();

        static::getContainer()->get('product.repository')->create([$product], $context);

        $output = ($this->entityUpsertTool)(
            entity: 'product',
            payload: json_encode([
                'id' => $ids->get('price-update'),
                'price' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 39.99,
                        'net' => 33.61,
                        'linked' => true,
                    ],
                ],
            ], \JSON_THROW_ON_ERROR),
            dryRun: false,
        );

        $data = $this->decodeToolOutput($output);
        static::assertFalse($data['_meta']['dryRun']);

        $readOutput = ($this->entityReadTool)(
            entity: 'product',
            id: $ids->get('price-update'),
        );

        $readData = $this->decodeToolOutput($readOutput);
        static::assertSame(39.99, $readData['data']['price'][0]['gross']);
    }

    public function testUS24CategoryAssignment(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $categoryId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $categoryId, 'name' => 'MCP Test Category US24'],
        ], $context);

        $product = (new ProductBuilder($ids, 'cat-assign'))
            ->price(10.00)
            ->visibility(TestDefaults::SALES_CHANNEL)
            ->build();

        static::getContainer()->get('product.repository')->create([$product], $context);

        $output = ($this->entityUpsertTool)(
            entity: 'product',
            payload: json_encode([
                'id' => $ids->get('cat-assign'),
                'categories' => [['id' => $categoryId]],
            ], \JSON_THROW_ON_ERROR),
            dryRun: false,
        );

        $this->decodeToolOutput($output);

        $readOutput = ($this->entityReadTool)(
            entity: 'product',
            id: $ids->get('cat-assign'),
            criteria: json_encode([
                'associations' => ['categories' => []],
            ], \JSON_THROW_ON_ERROR),
        );

        $readData = $this->decodeToolOutput($readOutput);
        $categoryIds = array_column($readData['data']['categories'], 'id');
        static::assertContains($categoryId, $categoryIds, 'Product should be assigned to the category');
    }
}
