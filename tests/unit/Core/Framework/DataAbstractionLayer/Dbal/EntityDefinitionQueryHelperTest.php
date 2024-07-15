<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(EntityDefinitionQueryHelper::class)]
class EntityDefinitionQueryHelperTest extends TestCase
{
    #[DataProvider('provideTestGetRoot')]
    public function testGetRoot(string $accessor, ?string $expectedRoot): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertEquals(
            $expectedRoot,
            EntityDefinitionQueryHelper::getRoot($accessor, $definition)
        );
    }

    #[DataProvider('provideGetAssociatedDefinition')]
    public function testGetAssociatedDefinition(string $accessor, string $expectedEntity): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertEquals(
            $expectedEntity,
            EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $accessor)->getEntityName()
        );
    }

    public static function provideTestGetRoot(): \Generator
    {
        yield 'no root' => ['name', null];
        yield 'with root' => ['categories.name', 'categories'];
        yield 'nested root' => ['product.categories.name', 'categories'];
        yield 'invalid root' => ['invalid.name', null];
    }

    public static function provideGetAssociatedDefinition(): \Generator
    {
        yield 'no root' => ['name', 'product'];
        yield 'with root' => ['categories.name', 'category'];
        yield 'many to many' => ['product.categories.name', 'category'];
        yield 'many to one' => ['product.manufacturer.name', 'product_manufacturer'];
        yield 'nested' => ['product.categories.translated.customFields.test', 'category'];
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductCategoryDefinition::class,
                CategoryTranslationDefinition::class,
                CategoryDefinition::class,
                ProductManufacturerDefinition::class,
                ProductTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }
}
