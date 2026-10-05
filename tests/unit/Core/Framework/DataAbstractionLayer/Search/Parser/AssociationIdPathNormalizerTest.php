<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AssociationIdPathNormalizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AssociationIdPathNormalizer::class)]
class AssociationIdPathNormalizerTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testNormalize(string $entity, string $field, string $expected): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductManufacturerDefinition::class,
                ProductVisibilityDefinition::class,
                SalesChannelDefinition::class,
                ProductCategoryDefinition::class,
                CategoryDefinition::class,
                ProductPriceDefinition::class,
                ProductCrossSellingDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        static::assertSame($expected, AssociationIdPathNormalizer::normalize($registry->getByEntityName($entity), $field));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'many to one id' => ['product', 'manufacturer.id', 'manufacturerId'];
        yield 'many to one id with root prefix' => ['product', 'product.manufacturer.id', 'product.manufacturerId'];
        yield 'nested many to one id' => ['product', 'visibilities.salesChannel.id', 'visibilities.salesChannelId'];
        yield 'own id' => ['product', 'id', 'id'];
        yield 'own id with root prefix' => ['product', 'product.id', 'product.id'];
        yield 'non id field' => ['product', 'manufacturer.name', 'manufacturer.name'];
        yield 'many to many id' => ['product', 'categories.id', 'categories.id'];
        yield 'unknown association' => ['product', 'foo.id', 'foo.id'];
        yield 'reverse inherited price product' => ['product_price', 'product.id', 'product.id'];
        yield 'reverse inherited cross selling product' => ['product_cross_selling', 'product.id', 'product.id'];
        yield 'price rule' => ['product_price', 'rule.id', 'ruleId'];
        yield 'nested reverse inherited' => ['product', 'prices.product.id', 'prices.product.id'];
    }
}
