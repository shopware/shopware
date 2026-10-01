<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\ProductFeatureSet;

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

    private ProductFeatureSetTranslationDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = static::getContainer()->get(ProductFeatureSetTranslationDefinition::class);
    }

    public function testEntityDefinitionIsComplete(): void
    {
        static::assertSame(ProductFeatureSetTranslationDefinition::ENTITY_NAME, $this->definition->getEntityName());
        static::assertSame(ProductFeatureSetTranslationCollection::class, $this->definition->getCollectionClass());
        static::assertSame(ProductFeatureSetTranslationEntity::class, $this->definition->getEntityClass());
    }

    #[TestWith(['name'])]
    #[TestWith(['description'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        static::assertTrue($this->definition->getFields()->has($field));
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
        static::assertInstanceOf(EntityRepository::class, static::getContainer()->get('product_feature_set_translation.repository'));
    }
}
