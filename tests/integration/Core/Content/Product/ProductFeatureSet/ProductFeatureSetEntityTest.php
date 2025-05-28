<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetCollection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('inventory')]
class ProductFeatureSetEntityTest extends TestCase
{
    use KernelTestBehaviour;

    private ProductFeatureSetDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = static::getContainer()->get(ProductFeatureSetDefinition::class);
    }

    public function testEntityDefinitionIsComplete(): void
    {
        static::assertSame(ProductFeatureSetDefinition::ENTITY_NAME, $this->definition->getEntityName());
        static::assertSame(ProductFeatureSetCollection::class, $this->definition->getCollectionClass());
        static::assertSame(ProductFeatureSetEntity::class, $this->definition->getEntityClass());
    }

    #[TestWith(['id'])]
    #[TestWith(['name'])]
    #[TestWith(['description'])]
    #[TestWith(['features'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        static::assertTrue($this->definition->getFields()->has($field));
    }

    #[TestWith(['getName'])]
    #[TestWith(['getDescription'])]
    #[TestWith(['getFeatures'])]
    #[TestWith(['getTranslations'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(ProductFeatureSetEntity::class, $method));
    }

    public function testRepositoryIsWorking(): void
    {
        static::assertInstanceOf(EntityRepository::class, static::getContainer()->get('product_feature_set.repository'));
    }

    public function testTranslationReferenceFieldIsCorrect(): void
    {
        $translationsField = static::getContainer()->get(ProductFeatureSetDefinition::class)->getField('translations');

        static::assertInstanceOf(TranslationsAssociationField::class, $translationsField);
        static::assertSame(
            \sprintf('%s_id', ProductFeatureSetDefinition::ENTITY_NAME),
            $translationsField->getReferenceField()
        );
    }
}
