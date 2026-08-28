<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpEntityIncludes;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpEntityIncludes::class)]
class McpEntityIncludesTest extends TestCase
{
    use McpEntityIncludes;

    public function testScalarFieldsAreIncluded(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                (new StringField('ean', 'ean'))->addFlags(new ApiAware()),
            ],
        ]);

        $includes = $this->buildDefaultIncludes($product, new Criteria());

        static::assertArrayHasKey('product', $includes);
        static::assertContains('id', $includes['product']);
        static::assertContains('name', $includes['product']);
        static::assertContains('ean', $includes['product']);
    }

    public function testUnrequestedAssociationsAreExcluded(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                new OneToManyAssociationField('categories', 'category', 'product_id'),
            ],
            'category' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
            ],
        ]);

        $includes = $this->buildDefaultIncludes($product, new Criteria());

        static::assertNotContains('categories', $includes['product']);
        static::assertArrayNotHasKey('category', $includes);
    }

    public function testRequestedAssociationsAreIncluded(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                (new FkField('manufacturer_id', 'manufacturerId', 'manufacturer'))->addFlags(new ApiAware()),
                new ManyToOneAssociationField('manufacturer', 'manufacturer_id', 'manufacturer'),
            ],
            'manufacturer' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('manufacturer');

        $includes = $this->buildDefaultIncludes($product, $criteria);

        static::assertContains('manufacturer', $includes['product']);
        static::assertContains('manufacturerId', $includes['product']);
        static::assertArrayHasKey('manufacturer', $includes);
        static::assertContains('id', $includes['manufacturer']);
        static::assertContains('name', $includes['manufacturer']);
    }

    public function testNestedAssociationsAreHandledRecursively(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                new OneToManyAssociationField('properties', 'property_group_option', 'product_id'),
            ],
            'property_group_option' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                (new FkField('property_group_id', 'groupId', 'property_group'))->addFlags(new ApiAware()),
                new ManyToOneAssociationField('group', 'property_group_id', 'property_group'),
            ],
            'property_group' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->getAssociation('properties')->addAssociation('group');

        $includes = $this->buildDefaultIncludes($product, $criteria);

        static::assertContains('properties', $includes['product']);
        static::assertArrayHasKey('property_group_option', $includes);
        static::assertContains('group', $includes['property_group_option']);
        static::assertArrayHasKey('property_group', $includes);
        static::assertContains('id', $includes['property_group']);
        static::assertContains('name', $includes['property_group']);
    }

    public function testDeepUnrequestedAssociationsAreExcluded(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                (new FkField('cover_id', 'coverId', 'product_media'))->addFlags(new ApiAware()),
                new ManyToOneAssociationField('cover', 'cover_id', 'product_media'),
            ],
            'product_media' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new FkField('media_id', 'mediaId', 'media'))->addFlags(new ApiAware()),
                new ManyToOneAssociationField('media', 'media_id', 'media'),
            ],
            'media' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('url', 'url'))->addFlags(new ApiAware()),
                new OneToManyAssociationField('thumbnails', 'media_thumbnail', 'media_id'),
            ],
            'media_thumbnail' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
                (new StringField('url', 'url'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->getAssociation('cover')->addAssociation('media');

        $includes = $this->buildDefaultIncludes($product, $criteria);

        static::assertContains('cover', $includes['product']);
        static::assertContains('media', $includes['product_media']);
        static::assertArrayHasKey('media', $includes);
        static::assertNotContains('thumbnails', $includes['media']);
        static::assertArrayNotHasKey('media_thumbnail', $includes);
    }

    public function testTranslatedFieldIsIncludedInDefaults(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                (new StringField('product_number', 'productNumber'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['product']);
        static::assertContains('id', $includes['product']);
        static::assertContains('productNumber', $includes['product']);
    }

    public function testTranslatedNotIncludedWhenNoTranslatedFields(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('product_number', 'productNumber'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertNotContains('translated', $includes['product']);
    }

    public function testApplyDefaultInjectsTranslatedIntoUserProvidedIncludes(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                (new StringField('product_number', 'productNumber'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['product' => ['id', 'name']]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['product']);
        static::assertContains('id', $includes['product']);
        static::assertContains('name', $includes['product']);
    }

    public function testApplyDefaultSkipsTranslatedWhenAlreadyPresent(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['product' => ['id', 'name', 'translated']]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertCount(1, array_keys($includes['product'], 'translated', true));
    }

    public function testApplyDefaultInjectsTranslatedRecursivelyIntoAssociations(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                (new FkField('manufacturer_id', 'manufacturerId', 'manufacturer'))->addFlags(new ApiAware()),
                new ManyToOneAssociationField('manufacturer', 'manufacturer_id', 'manufacturer'),
            ],
            'manufacturer' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('manufacturer');
        $criteria->setIncludes([
            'product' => ['id', 'name', 'manufacturer'],
            'manufacturer' => ['id', 'name'],
        ]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['product']);
        static::assertContains('translated', $includes['manufacturer']);
    }

    public function testManyToManyAssociationResolvesReferenceDefinition(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                (new ManyToManyIdField('category_ids', 'categoryIds', 'categories'))->addFlags(new ApiAware()),
                new ManyToManyAssociationField('categories', 'category', 'product_category', 'product_id', 'category_id'),
            ],
            'category' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
            ],
            'product_category' => [
                (new FkField('product_id', 'productId', 'product'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new FkField('category_id', 'categoryId', 'category'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('categories');

        $includes = $this->buildDefaultIncludes($product, $criteria);

        static::assertContains('categories', $includes['product']);
        static::assertArrayHasKey('category', $includes);
        static::assertContains('id', $includes['category']);
        static::assertContains('name', $includes['category']);
    }

    public function testRecursionGuardPreventsInfiniteLoop(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                (new FkField('manufacturer_id', 'manufacturerId', 'manufacturer'))->addFlags(new ApiAware()),
                new ManyToOneAssociationField('manufacturer', 'manufacturer_id', 'manufacturer'),
            ],
            'manufacturer' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('name', 'name'))->addFlags(new ApiAware()),
                new OneToManyAssociationField('products', 'product', 'manufacturer_id'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->getAssociation('manufacturer')->addAssociation('products');

        $includes = $this->buildDefaultIncludes($product, $criteria);

        static::assertContains('manufacturer', $includes['product']);
        static::assertArrayHasKey('manufacturer', $includes);
        static::assertContains('products', $includes['manufacturer']);
        static::assertArrayHasKey('product', $includes);
    }

    public function testEnsureTranslatedRecursivelyTraversesManyToManyAssociations(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new ApiAware()),
                new ManyToManyAssociationField('tags', 'tag', 'product_tag', 'product_id', 'tag_id'),
            ],
            'tag' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
            'product_tag' => [
                (new FkField('product_id', 'productId', 'product'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new FkField('tag_id', 'tagId', 'tag'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('tags');
        $criteria->setIncludes([
            'product' => ['id', 'name', 'tags'],
            'tag' => ['id', 'name'],
        ]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['product']);
        static::assertContains('translated', $includes['tag']);
    }

    public function testEnsureTranslatedSkipsEntityNotInIncludesMap(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['other_entity' => ['id']]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertArrayNotHasKey('product', $includes);
        static::assertArrayHasKey('other_entity', $includes);
    }

    public function testCollectIncludesStopsOnAlreadyVisitedEntity(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new ManyToOneAssociationField('manufacturer', 'manufacturer_id', 'manufacturer', 'id'))->addFlags(new ApiAware()),
                (new ManyToOneAssociationField('cover', 'cover_id', 'media', 'id'))->addFlags(new ApiAware()),
            ],
            'manufacturer' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new ManyToOneAssociationField('logo', 'logo_id', 'media', 'id'))->addFlags(new ApiAware()),
            ],
            'media' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('file_name', 'fileName'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('manufacturer');
        $criteria->getAssociation('manufacturer')->addAssociation('logo');
        $criteria->addAssociation('cover');

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertArrayHasKey('product', $includes);
        static::assertArrayHasKey('manufacturer', $includes);
        static::assertArrayHasKey('media', $includes);
    }

    public function testAddTranslatedSkipsNonAssociationCriteriaKey(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                new TranslatedField('name'),
                (new StringField('product_number', 'productNumber'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addAssociation('nonExistentField');
        $criteria->setIncludes(['product' => ['id', 'name']]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertContains('translated', $includes['product']);
    }

    public function testEnsureTranslatedSkipsEntityWithoutTranslatedFields(): void
    {
        [$product] = $this->compileDefinitions([
            'product' => [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new StringField('product_number', 'productNumber'))->addFlags(new ApiAware()),
            ],
        ]);

        $criteria = new Criteria();
        $criteria->setIncludes(['product' => ['id', 'productNumber']]);

        $this->applyDefaultIncludes($product, $criteria);

        $includes = $criteria->getIncludes();
        static::assertNotNull($includes);
        static::assertNotContains('translated', $includes['product']);
        static::assertSame(['id', 'productNumber'], $includes['product']);
    }

    /**
     * @param array<non-empty-string, list<Field>> $definitionsMap
     *
     * @return list<EntityDefinition>
     */
    private function compileDefinitions(array $definitionsMap): array
    {
        $definitions = [];

        foreach ($definitionsMap as $entityName => $fields) {
            $fieldCollection = new FieldCollection($fields);

            /** @phpstan-ignore method.deprecated */
            $definitions[$entityName] = new class($entityName, $fieldCollection) extends EntityDefinition {
                /**
                 * @param non-empty-string $name
                 */
                public function __construct(
                    private readonly string $name,
                    private readonly FieldCollection $fieldList,
                ) {
                }

                public function getEntityName(): string
                {
                    return $this->name;
                }

                protected function defineFields(): FieldCollection
                {
                    return $this->fieldList;
                }
            };
        }

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByClassOrEntityName')->willReturnCallback(
            function (string $classOrName) use ($definitions): EntityDefinition {
                foreach ($definitions as $def) {
                    if ($def::class === $classOrName || $def->getEntityName() === $classOrName) {
                        return $def;
                    }
                }

                throw new \RuntimeException('Definition not found: ' . $classOrName);
            }
        );

        foreach ($definitions as $def) {
            $def->compile($registry);
        }

        return array_values($definitions);
    }
}
