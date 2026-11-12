<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\ContentSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class ProductVariantSelectionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->browser = $this->createSalesChannelBrowser();
        $this->createProductFamily('page', 'Color', 'Red');
        $this->createProductFamily('chosen', 'Size', 'Large');
    }

    #[DataProvider('productSelections')]
    public function testProductAndOptionsFollowTheSameSelection(bool $mapped, string $pickedProduct, string $expectedProduct, string $expectedFamily): void
    {
        $this->saveLayout($this->selector($mapped, $this->ids->get($pickedProduct)));

        $properties = $this->properties();

        static::assertSame($this->ids->get($expectedProduct), $properties['product']['id']);
        static::assertSame([$this->ids->get($expectedFamily . '-group')], array_column($properties['configuratorSettings'], 'id'));
        static::assertSame([$this->ids->get($expectedFamily . '-option')], array_column($properties['configuratorSettings'][0]['options'], 'id'));
        static::assertTrue($properties['configuratorSettings'][0]['options'][0]['combinable']);
    }

    /**
     * @return iterable<string, array{bool, string, string, string}>
     */
    public static function productSelections(): iterable
    {
        yield 'root mapping wins over a saved custom selection' => [true, 'chosen-variant', 'page', 'page'];
        yield 'custom variant uses its own parent groups' => [false, 'chosen-variant', 'chosen-variant', 'chosen'];
        yield 'custom parent uses its own groups' => [false, 'chosen', 'chosen', 'chosen'];
    }

    public function testSwitchingBackToThePageProductAlsoSwitchesItsOptions(): void
    {
        $this->saveLayout($this->selector(mapped: false, productId: $this->ids->get('chosen-variant')));
        static::assertSame($this->ids->get('chosen-group'), $this->properties()['configuratorSettings'][0]['id']);

        $this->saveLayout($this->selector(mapped: true, productId: $this->ids->get('chosen-variant')));
        $properties = $this->properties();

        static::assertSame($this->ids->get('page'), $properties['product']['id']);
        static::assertSame($this->ids->get('page-group'), $properties['configuratorSettings'][0]['id']);
    }

    public function testMissingCustomProductDoesNotKeepPageOptions(): void
    {
        $this->saveLayout($this->selector(mapped: false, productId: Uuid::randomHex()));

        $properties = $this->properties();

        static::assertNull($properties['product']);
        static::assertNull($properties['configuratorSettings']);
    }

    public function testOptionsDoNotRequirePageConfiguratorWiring(): void
    {
        $selector = $this->selector(mapped: false, productId: $this->ids->get('chosen-variant'));
        unset($selector['acceptsContext']['configuratorSettings']);
        $this->saveLayout($selector);

        static::assertSame($this->ids->get('chosen-group'), $this->properties()['configuratorSettings'][0]['id']);
    }

    public function testPartialRenderingLoadsOptionsForTheSelectedProduct(): void
    {
        $this->saveLayout($this->selector(mapped: false, productId: $this->ids->get('chosen-variant')));

        $body = $this->responseBody('content', ['elementId' => $this->ids->get('selector')]);

        static::assertCount(1, $body['elements']);
        static::assertSame($this->ids->get('selector'), $body['elements'][0]['id']);
        static::assertSame($this->ids->get('chosen-group'), $body['elements'][0]['properties']['configuratorSettings'][0]['id']);
    }

    #[DataProvider('indexedFormats')]
    public function testIndexedResponsesIncludeTheSelectedProductsOptions(string $format): void
    {
        $this->saveLayout($this->selector(mapped: false, productId: $this->ids->get('chosen-variant')));

        $body = $this->responseBody($format);

        $references = $body['assignments'][$this->ids->get('selector')];
        static::assertSame($this->ids->get('chosen-variant'), $body['data'][$references['product']]['id']);
        static::assertSame($this->ids->get('chosen-group'), $body['data'][$references['configuratorSettings']][0]['id']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function indexedFormats(): iterable
    {
        yield 'data format' => ['content-data'];
        yield 'decomposed format' => ['content-decomposed'];
    }

    private function createProductFamily(string $key, string $groupName, string $optionName): void
    {
        $this->repository('product.repository')->create([[
            'id' => $this->ids->create($key),
            'name' => $key,
            'productNumber' => 'variant-selection-' . $key,
            'stock' => 10,
            'active' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => $key . '-tax', 'taxRate' => 19],
            'visibilities' => [[
                'salesChannelId' => $this->browser->getServerParameter('test-sales-channel-id'),
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
            'configuratorSettings' => [[
                'option' => [
                    'id' => $this->ids->create($key . '-option'),
                    'name' => $optionName,
                    'group' => ['id' => $this->ids->create($key . '-group'), 'name' => $groupName],
                ],
            ]],
            'children' => [[
                'id' => $this->ids->create($key . '-variant'),
                'productNumber' => 'variant-selection-' . $key . '-child',
                'stock' => 10,
                'active' => true,
                'options' => [['id' => $this->ids->get($key . '-option')]],
            ]],
        ]], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function selector(bool $mapped, string $productId): array
    {
        $consumers = [
            'configuratorSettings' => ['type' => 'collection', 'scope' => 'root', 'required' => false],
        ];
        if ($mapped) {
            $consumers['product'] = [
                'type' => 'single',
                'scope' => 'root',
                'required' => false,
                'source' => ['type' => 'root', 'id' => 'product'],
            ];
        }

        return [
            'id' => $this->ids->get('selector'),
            'component' => 'Sw:Product:VariantSelection',
            'properties' => ['productId' => $productId],
            'dataRequirements' => ['product' => ['source' => 'entity', 'config' => ['entity' => 'product', 'property' => 'productId']]],
            'acceptsContext' => $consumers,
        ];
    }

    /**
     * @param array<string, mixed> $selector
     */
    private function saveLayout(array $selector): void
    {
        $this->repository('content_layout.repository')->upsert([[
            'id' => $this->ids->get('layout'),
            'name' => 'variant-selection',
            'version' => '1',
            'rootSource' => 'product',
            'layout' => [[
                'id' => $this->ids->get('grid'),
                'component' => 'Sw:Grid:Container',
                'slots' => ['content' => [$selector]],
            ]],
        ]], Context::createDefaultContext());
        $this->repository('product_content_layout.repository')->upsert([[
            'id' => $this->ids->get('assignment'),
            'productId' => $this->ids->get('page'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(): array
    {
        $body = $this->responseBody();
        $properties = $body['elements'][0]['slots']['content'][0]['properties'] ?? null;
        static::assertIsArray($properties);

        return $properties;
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function responseBody(string $format = 'content', array $query = []): array
    {
        $this->browser->request('GET', '/store-api/' . $format . '/product/' . $this->ids->get('page'), $query);
        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        return $body;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
