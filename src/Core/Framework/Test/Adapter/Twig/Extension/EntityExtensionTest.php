<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\Extension\EntityExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\Adapter\Twig\Extension\TestDefinition\TestReadProtectedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twig\Loader\ArrayLoader;

class EntityExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private DefinitionInstanceRegistry $definitionRegistry;

    private SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $this->salesChannelDefinitionRegistry = $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
    }

    public function testSearchEntityWithoutContext(): void
    {
        static::expectException(\InvalidArgumentException::class);
        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $extension->searchEntities([], 'product_manufacturer', [], []);
    }

    public function testSearchEntityWithDefaultContext(): void
    {
        $context = Context::createDefaultContext();

        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $manufacturers = $extension->searchEntities(['context' => $context], 'product_manufacturer', [], []);

        static::assertInstanceOf(ProductManufacturerCollection::class, $manufacturers);
        static::assertEmpty($manufacturers);
    }

    public function testSearchEntityWithDefaultContextLoadAssociation(): void
    {
        $context = Context::createDefaultContext();

        $manufacturerId = $this->createManufacturer($context);

        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $manufacturers = $extension->searchEntities(['context' => $context], 'product_manufacturer', [$manufacturerId], ['media']);

        static::assertInstanceOf(ProductManufacturerCollection::class, $manufacturers);
        static::assertCount(1, $manufacturers);
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturers->first());

        /** @var ProductManufacturerEntity $manufacturer */
        $manufacturer = $manufacturers->first();
        static::assertEquals('testImage', $manufacturer->getMedia()->getFileName());
    }

    public function testSearchEntityWithSalesChannelDefinitionByDefaultContext(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();
        $productId = $this->createProduct($salesChannelContext);

        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $products = $extension->searchEntities(['context' => $salesChannelContext->getContext()], 'product', [$productId], []);

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertInstanceOf(ProductEntity::class, $products->first());
    }

    public function testSearchEntityWithSalesChannelDefinition(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();
        $productId = $this->createProduct($salesChannelContext);

        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $products = $extension->searchEntities(['context' => $salesChannelContext], 'product', [$productId], []);
        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertInstanceOf(SalesChannelProductEntity::class, $products->first());
    }

    public function testSearchEntityWithSalesChannelDefinitionLoadAssociation(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();
        $productId = $this->createProduct($salesChannelContext);

        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $products = $extension->searchEntities(['context' => $salesChannelContext], 'product', [$productId], ['manufacturer']);
        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertInstanceOf(SalesChannelProductEntity::class, $products->first());

        /** @var ProductEntity $product */
        $product = $products->first();
        static::assertEquals('98432def39fc4624b33213a56b8c944d', $product->getManufacturer()->getId());
        static::assertEquals('test', $product->getManufacturer()->getName());
    }

    public function testSearchEntityHasReadProtected(): void
    {
        static::expectException(AccessDeniedHttpException::class);
        $context = Context::createDefaultContext();
        $this->registerDefinition(TestReadProtectedDefinition::class);
        $extension = new EntityExtension($this->salesChannelDefinitionRegistry, $this->definitionRegistry);

        $extension->searchEntities(['context' => $context], '_test_read_protected', [Uuid::randomHex()], []);
    }

    public function testRenderEntitiesExtension(): void
    {
        $context = Context::createDefaultContext();
        $manufacturerAId = $this->createManufacturer($context, 'Manufacturer A');
        $manufacturerBId = $this->createManufacturer($context, 'Manufacturer B');

        $result = $this->render('entity.html.twig', [
            'entity' => 'product_manufacturer',
            'ids' => [
                $manufacturerAId,
                $manufacturerBId,
            ],
            'association' => ['media'],
            'context' => $context,
        ]);

        static::assertEquals('Manufacturer A/testImage/Manufacturer B/testImage/', $result);
    }

    private function render(string $template, array $data): string
    {
        $twig = $this->getContainer()->get('twig');

        $originalLoader = $twig->getLoader();
        $twig->setLoader(new ArrayLoader([
            'test.html.twig' => file_get_contents(__DIR__ . '/fixture/' . $template),
        ]));
        $output = $twig->render('test.html.twig', $data);
        $twig->setLoader($originalLoader);

        return $output;
    }

    private function createManufacturer(Context $context, $name = null): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => $name ?: 'Manufacturer',
            'link' => 'http://domain.example',
            'media' => [
                'id' => UUid::randomHex(),
                'fileName' => 'testImage',
            ],
        ];

        $this->getContainer()->get('product_manufacturer.repository')->create([$data], $context);

        return $id;
    }

    private function createProduct(SalesChannelContext $context): string
    {
        $productId = Uuid::randomHex();

        $data = [
            'id' => $productId,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => '98432def39fc4624b33213a56b8c944d', 'name' => 'test'],
            'tax' => [
                'id' => $context->getTaxRules()->first()->getId(),
            ],
            'visibilities' => [
                ['salesChannelId' => $context->getSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$data], $context->getContext());

        return $productId;
    }
}
