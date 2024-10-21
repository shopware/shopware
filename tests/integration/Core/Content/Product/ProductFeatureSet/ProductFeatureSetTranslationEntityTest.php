<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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

    #[DataProvider('definitionMethodProvider')]
    public function testEntityDefinitionIsComplete(string $method, string $returnValue): void
    {
        $definition = $this->getContainer()->get(ProductFeatureSetTranslationDefinition::class);

        static::assertTrue(method_exists($definition, $method));
        static::assertEquals($returnValue, $definition->$method()); /* @phpstan-ignore-line */
    }

    #[TestWith(['name'])]
    #[TestWith(['description'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        $definition = $this->getContainer()->get(ProductFeatureSetTranslationDefinition::class);

        static::assertTrue($definition->getFields()->has($field));
    }

    #[TestWith(['getProductFeatureSetId'])]
    #[TestWith(['getName'])]
    #[TestWith(['getDescription'])]
    #[TestWith(['getProductFeatureSet'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(ProductFeatureSetTranslationEntity::class, $method));
    }

    public function testRepositoryIsWorking(): void
    {
        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('product_feature_set_translation.repository'));
    }

    /**
     * @return list<array<string>>
     */
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
