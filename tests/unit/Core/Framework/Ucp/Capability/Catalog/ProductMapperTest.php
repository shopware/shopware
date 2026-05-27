<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductMapper;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Pins the wire-format of the UCP product schema. The mapper is the only
 * adapter between Shopware's `SalesChannelProductEntity` and the platform-
 * facing payload, so the keys, types, and minor-units conversion must stay
 * stable across UCP versions.
 *
 * @internal
 */
#[CoversClass(ProductMapper::class)]
class ProductMapperTest extends TestCase
{
    public function testMapsBasicProductWithSingleVariant(): void
    {
        $product = $this->makeProduct(
            id: 'product-1',
            number: 'SKU-1',
            name: 'Test Product',
            description: 'A great <strong>product</strong>',
            stock: 5,
            unitPrice: 19.99,
        );

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertSame('SKU-1', $ucp['id']);
        static::assertSame('Test Product', $ucp['title']);
        static::assertSame(['plain' => 'A great product'], $ucp['description']);
        static::assertCount(1, $ucp['variants']);

        $variant = $ucp['variants'][0];
        static::assertSame('SKU-1', $variant['id']);
        static::assertSame('SKU-1', $variant['sku']);
        static::assertSame(['amount' => 1999, 'currency' => 'EUR'], $variant['price']);
        static::assertSame(
            ['available' => true, 'status' => 'in_stock', 'available_quantity' => 5],
            $variant['availability']
        );
    }

    public function testMarksOutOfStockWhenStockIsZero(): void
    {
        $product = $this->makeProduct(
            id: 'product-2',
            number: 'SKU-2',
            name: 'Empty',
            stock: 0,
        );

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertFalse($ucp['variants'][0]['availability']['available']);
        static::assertSame('out_of_stock', $ucp['variants'][0]['availability']['status']);
        static::assertSame(0, $ucp['variants'][0]['availability']['available_quantity']);
    }

    public function testEmitsManufacturerAsBrandWhenPresent(): void
    {
        $product = $this->makeProduct(id: 'p3', number: 'SKU-3', name: 'Branded');
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setName('Acme Inc.');
        $product->setManufacturer($manufacturer);

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertSame('Acme Inc.', $ucp['brand']);
    }

    public function testMapsChildVariantsWhenPresent(): void
    {
        $parent = $this->makeProduct(id: 'parent', number: 'PARENT-1', name: 'Parent', stock: 0);
        $child1 = $this->makeProduct(id: 'c1', number: 'CHILD-1', name: 'Child 1', stock: 3, unitPrice: 10.0);
        $child2 = $this->makeProduct(id: 'c2', number: 'CHILD-2', name: 'Child 2', stock: 0, unitPrice: 12.5);

        $children = new ProductCollection([$child1, $child2]);
        $parent->setChildren($children);

        $ucp = (new ProductMapper())->toUcpProduct($parent, $this->context('USD'));

        static::assertCount(2, $ucp['variants']);
        static::assertSame('CHILD-1', $ucp['variants'][0]['sku']);
        static::assertSame('CHILD-2', $ucp['variants'][1]['sku']);
        static::assertSame(1000, $ucp['variants'][0]['price']['amount']);
        static::assertSame(1250, $ucp['variants'][1]['price']['amount']);
        static::assertSame('USD', $ucp['variants'][0]['price']['currency']);
    }

    public function testCollectsMediaUrlsAsImages(): void
    {
        $product = $this->makeProduct(id: 'm1', number: 'M-1', name: 'WithMedia');

        $media1 = new MediaEntity();
        $media1->setId('media-1');
        $media1->setUrl('https://cdn.example/m1.jpg');

        $media2 = new MediaEntity();
        $media2->setId('media-2');
        $media2->setUrl('https://cdn.example/m2.jpg');

        $assoc1 = new ProductMediaEntity();
        $assoc1->setId('a1');
        $assoc1->setMedia($media1);
        $assoc2 = new ProductMediaEntity();
        $assoc2->setId('a2');
        $assoc2->setMedia($media2);

        $product->setMedia(new ProductMediaCollection([$assoc1, $assoc2]));

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertSame([
            ['url' => 'https://cdn.example/m1.jpg', 'type' => 'image'],
            ['url' => 'https://cdn.example/m2.jpg', 'type' => 'image'],
        ], $ucp['media']);
    }

    public function testOmitsMediaWhenAssociationHasNoUrls(): void
    {
        $product = $this->makeProduct(id: 'm0', number: 'M-0', name: 'NoMedia');
        $assoc = new ProductMediaEntity();
        $assoc->setId('a0');
        $assoc->setMedia(new MediaEntity());
        $product->setMedia(new ProductMediaCollection([$assoc]));

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertArrayNotHasKey('media', $ucp);
    }

    public function testFallsBackToIdWhenProductNumberIsEmpty(): void
    {
        $product = $this->makeProduct(id: 'uuid-only-product', number: '', name: 'NoSku');

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertSame('uuid-only-product', $ucp['id']);
    }

    public function testOmitsDescriptionWhenNullOrEmpty(): void
    {
        $product = $this->makeProduct(id: 'p', number: 'P-1', name: 'NoDesc', description: null);

        $ucp = (new ProductMapper())->toUcpProduct($product, $this->context('EUR'));

        static::assertArrayNotHasKey('description', $ucp);
    }

    private function makeProduct(
        string $id,
        string $number,
        string $name,
        ?string $description = null,
        int $stock = 1,
        float $unitPrice = 9.99,
    ): SalesChannelProductEntity {
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setProductNumber($number);
        $product->setName($name);
        $product->setDescription($description);
        $product->setAvailableStock($stock);
        $product->setStock($stock);
        $product->setCalculatedPrice(new CalculatedPrice(
            $unitPrice,
            $unitPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        return $product;
    }

    private function context(string $currencyIso): SalesChannelContext
    {
        $currency = new CurrencyEntity();
        $currency->setId('currency-' . $currencyIso);
        $currency->setIsoCode($currencyIso);

        $ctx = $this->createMock(SalesChannelContext::class);
        $ctx->method('getCurrency')->willReturn($currency);

        return $ctx;
    }
}
