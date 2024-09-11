<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('definitionMethodProvider')]
    public function testEntityDefinitionIsComplete(string $method, string $returnValue): void
    {
        $definition = $this->getContainer()->get(ProductFeatureSetDefinition::class);

        static::assertTrue(method_exists($definition, $method));
        static::assertEquals($returnValue, $definition->$method()); /* @phpstan-ignore-line */
    }

    #[TestWith(['id'])]
    #[TestWith(['name'])]
    #[TestWith(['description'])]
    #[TestWith(['features'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        $definition = $this->getContainer()->get(ProductFeatureSetDefinition::class);

        static::assertTrue($definition->getFields()->has($field));
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
        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('product_feature_set.repository'));
    }

    public function testTranslationReferenceFieldIsCorrect(): void
    {
        $translationsField = $this->getContainer()->get(ProductFeatureSetDefinition::class)->getField('translations');

        static::assertInstanceOf(TranslationsAssociationField::class, $translationsField);
        static::assertEquals(
            \sprintf('%s_id', ProductFeatureSetDefinition::ENTITY_NAME),
            $translationsField->getReferenceField()
        );
    }

    /**
     * @return list<array<string>>
     */
    public static function definitionMethodProvider(): array
    {
        return [
            [
                'getEntityName',
                'product_feature_set',
            ],
            [
                'getCollectionClass',
                ProductFeatureSetCollection::class,
            ],
            [
                'getEntityClass',
                ProductFeatureSetEntity::class,
            ],
        ];
    }
}
