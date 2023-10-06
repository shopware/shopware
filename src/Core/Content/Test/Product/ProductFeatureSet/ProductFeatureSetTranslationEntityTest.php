<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\ProductFeatureSet;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('inventory')]
class ProductFeatureSetTranslationEntityTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityDefinitionExists(): void
    {
        static::assertInstanceOf(
            ProductFeatureSetTranslationDefinition::class,
            new ProductFeatureSetTranslationDefinition()
        );
    }

    /**
     * @dataProvider definitionMethodProvider
     */
    public function testEntityDefinitionIsComplete(string $method, string $returnValue): void
    {
        $definition = $this->getContainer()->get(ProductFeatureSetTranslationDefinition::class);

        static::assertTrue(method_exists($definition, $method));
        static::assertEquals($returnValue, $definition->$method()); /* @phpstan-ignore-line */
    }

    /**
     * @testWith    ["name"]
     *              ["description"]
     */
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        $definition = $this->getContainer()->get(ProductFeatureSetTranslationDefinition::class);

        static::assertTrue($definition->getFields()->has($field));
    }

    public function testEntityExists(): void
    {
        static::assertInstanceOf(
            ProductFeatureSetTranslationEntity::class,
            new ProductFeatureSetTranslationEntity()
        );
    }

    /**
     * @testWith    ["getProductFeatureSetId"]
     *              ["getName"]
     *              ["getDescription"]
     *              ["getProductFeatureSet"]
     */
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(ProductFeatureSetTranslationEntity::class, $method));
    }

    public function testCollectionExists(): void
    {
        static::assertInstanceOf(
            ProductFeatureSetTranslationCollection::class,
            new ProductFeatureSetTranslationCollection()
        );
    }

    public function testRepositoryIsWorking(): void
    {
        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('product_feature_set_translation.repository'));
    }

    public static function definitionMethodProvider(): array
    {
        return [
            [
                'getEntityName',
                'product_feature_set_translation',
            ],
            [
                'getCollectionClass',
                ProductFeatureSetTranslationCollection::class,
            ],
            [
                'getEntityClass',
                ProductFeatureSetTranslationEntity::class,
            ],
        ];
    }
}
