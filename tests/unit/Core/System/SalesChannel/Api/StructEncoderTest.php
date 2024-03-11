<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(StructEncoder::class)]
class StructEncoderTest extends TestCase
{
    /**
     * Regression test where the cheapest price and cheapest price container were exposed because the StructEncoder did not consider sales channel definitions
     */
    public function testCheapestPricesAreNotExposed(): void
    {
        $product = new SalesChannelProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $product->setCheapestPrice(
            (new CheapestPrice())->assign([
                'hasRange' => false,
                'variantId' => Uuid::randomHex(),
                'parentId' => Uuid::randomHex(),
                'ruleId' => Uuid::randomHex(),
                'purchase' => 1.0,
                'reference' => 1.0,
                'price' => new PriceCollection(),
            ])
        );

        $registry = new StaticDefinitionInstanceRegistry(
            [SalesChannelProductDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        $encoded = (new StructEncoder($this->getChainRegistry($registry), $serializer))->encode($product, new ResponseFields(null));

        static::assertArrayNotHasKey('cheapestPrice', $encoded);
        static::assertArrayHasKey('name', $encoded);
        static::assertEquals('test', $encoded['name']);
    }

    public function testNoneMappedFieldsAreNotExposed(): void
    {
        $product = new ExtendedProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $registry = new StaticDefinitionInstanceRegistry(
            [SalesChannelProductDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        $encoded = (new StructEncoder($this->getChainRegistry($registry), $serializer))->encode($product, new ResponseFields(null));

        static::assertArrayNotHasKey('notExposed', $encoded);
        static::assertArrayHasKey('name', $encoded);
        static::assertEquals('test', $encoded['name']);
    }

    public function testExtensionAreSupported(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ExtensionDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $manufacturer = (new ProductManufacturerEntity())->assign(['name' => 'foo']);
        $product->addExtension('exposedExtension', $manufacturer);
        $product->addExtension('notExposedExtension', $manufacturer);
        $product->setName('test');

        $product->addExtension('foreignKeys', new ArrayStruct(['exposedFk' => 'exposed', 'notExposedFk' => 'not_exposed'], 'product'));
        $product->addExtension('search', new ArrayEntity(['score' => 2000]));

        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        $encoded = (new StructEncoder($this->getChainRegistry($registry), $serializer))->encode($product, new ResponseFields(null));

        static::assertArrayHasKey('extensions', $encoded);
        static::assertArrayHasKey('exposedExtension', $encoded['extensions']);
        static::assertArrayHasKey('search', $encoded['extensions']);
        static::assertArrayNotHasKey('notExposedExtension', $encoded['extensions']);
        static::assertArrayHasKey('foreignKeys', $encoded['extensions']);

        static::assertArrayHasKey('score', $encoded['extensions']['search']);
        static::assertArrayHasKey('exposedFk', $encoded['extensions']['foreignKeys']);
        static::assertArrayNotHasKey('notExposedFk', $encoded['extensions']['foreignKeys']);
    }

    public function testPayloadProtection(): void
    {
        $cart = new Cart('test');

        $item = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE, 'test');

        $item->setPayload(['foo' => 'bar', 'bar' => 'foo'], ['foo' => false, 'bar' => true]);

        $cart->add($item);

        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        $encoded = (new StructEncoder($this->createMock(DefinitionRegistryChain::class), $serializer))
            ->encode($cart, new ResponseFields(null));

        static::assertArrayHasKey('lineItems', $encoded);
        static::assertArrayHasKey(0, $encoded['lineItems']);
        static::assertArrayHasKey('payload', $encoded['lineItems'][0]);
        static::assertIsArray($encoded['lineItems'][0]['payload']);
        static::assertArrayHasKey('foo', $encoded['lineItems'][0]['payload']);
        static::assertArrayNotHasKey('bar', $encoded['lineItems'][0]['payload']);
    }

    private function getChainRegistry(StaticDefinitionInstanceRegistry $registry): DefinitionRegistryChain
    {
        $mock = $this->createMock(ContainerInterface::class);

        return new DefinitionRegistryChain($registry, new SalesChannelDefinitionInstanceRegistry('', $mock, [], []));
    }
}

/**
 * @internal
 */
class ExtendedProductEntity extends ProductEntity
{
    public string $notExposed = 'test';
}

/**
 * @internal
 */
class ExtensionDefinition extends ProductDefinition
{
    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new ManyToOneAssociationField('exposedExtension', 'my_extension_id', ProductManufacturerDefinition::class))->addFlags(new Extension(), new ApiAware())
        );
        $fields->add(
            (new ManyToOneAssociationField('notExposedExtension', 'my_extension_id', ProductManufacturerDefinition::class))->addFlags(new Extension())
        );
        $fields->add(
            (new FkField('exposed_fk', 'exposedFk', ProductManufacturerDefinition::class))->addFlags(new Extension(), new ApiAware())
        );
        $fields->add(
            (new FkField('not_exposed_fk', 'notExposedFk', ProductManufacturerDefinition::class))->addFlags(new Extension())
        );

        return $fields;
    }
}
