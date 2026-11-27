<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class EntityDefinitionQueryHelperTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    #[DataProvider('jsonAccessorProvider')]
    public function testJsonAccessorPreservesExtensions(string $accessor): void
    {
        $this->registerDefinitionWithExtensions(CategoryDefinition::class, JsonAccessorCategoryExtension::class);

        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('category.repository');
        $nestedId = Uuid::randomHex();
        $flatId = Uuid::randomHex();
        $nestedParentId = Uuid::randomHex();
        $flatParentId = Uuid::randomHex();
        $nested = [
            'extensions' => ['foo' => 'red', 'extensions' => ['foo' => 'red']],
            'myextensions' => ['foo' => 'red'],
            'foo' => ['extensions' => ['foo' => 'red']],
        ];
        $flat = ['foo' => 'red', 'myfoo' => 'red'];

        $repository->create([
            ['id' => $nestedId, 'name' => 'Nested JSON', 'parent' => ['id' => $nestedParentId, 'name' => 'Nested parent']],
            ['id' => $flatId, 'name' => 'Flat JSON', 'parent' => ['id' => $flatParentId, 'name' => 'Flat parent']],
        ], $context);

        // The custom-field serializer currently consumes the extensions key on writes.
        $connection = static::getContainer()->get(Connection::class);
        foreach ([$nestedId => $nested, $nestedParentId => $nested, $flatId => $flat, $flatParentId => $flat] as $id => $customFields) {
            $connection->update('category_translation', ['custom_fields' => json_encode($customFields, \JSON_THROW_ON_ERROR)], ['category_id' => Uuid::fromHexToBytes($id)]);
        }

        $criteria = new Criteria([$nestedId, $flatId]);
        $criteria->addFilter(new EqualsFilter($accessor, 'red'));

        static::assertSame([$nestedId], $repository->searchIds($criteria, $context)->getIds());

        $criteria = new Criteria([$nestedId, $flatId]);
        $criteria->addSorting(new FieldSorting($accessor));

        static::assertSame([$flatId, $nestedId], $repository->searchIds($criteria, $context)->getIds());

        $criteria = new Criteria([$nestedId, $flatId]);
        $criteria->addAggregation(new TermsAggregation('values', $accessor));
        $result = $repository->aggregate($criteria, $context)->get('values');

        static::assertInstanceOf(TermsResult::class, $result);
        $bucket = $result->get('red');
        static::assertNotNull($bucket);
        static::assertSame(1, $bucket->getCount());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jsonAccessorProvider(): iterable
    {
        yield 'JSON key named extensions' => ['customFields.extensions.foo'];
        yield 'root-qualified JSON path' => ['category.customFields.extensions.foo'];
        yield 'repeated JSON keys' => ['customFields.extensions.extensions.foo'];
        yield 'JSON key containing extensions' => ['customFields.myextensions.foo'];
        yield 'extensions deeper inside JSON' => ['customFields.foo.extensions.foo'];
        yield 'JSON through an association' => ['parent.customFields.extensions.foo'];
        yield 'JSON through an extension association' => ['extensions.extensionParent.customFields.extensions.foo'];
        yield 'root-qualified extension association' => ['category.extensions.extensionParent.customFields.extensions.foo'];
        yield 'extension after a to-many association' => ['parent.children.extensions.extensionParent.customFields.extensions.foo'];
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('getData')]
    public function testGetFieldsOfAccessor(
        string $class,
        string $accessor,
        array $expected,
        bool $resolveTranslated = true
    ): void {
        $definition = static::getContainer()->get(DefinitionInstanceRegistry::class)->get($class);
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor, $resolveTranslated);

        $actual = [];
        foreach ($fields as $field) {
            $actual[] = $field->getPropertyName();
        }

        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|string|false>>
     */
    public static function getData(): iterable
    {
        yield 'With product prefix and skippable fields' => [
            ProductDefinition::class,
            'product.options.first.group.extension.foo.bar',
            [
                'options',
                'group',
            ],
        ];

        yield 'With skippable field' => [
            ProductDefinition::class,
            'options.first.group.name',
            [
                'options',
                'group',
                'name',
            ],
        ];

        yield 'With translations field' => [
            ProductDefinition::class,
            'translations.description',
            [
                'translations',
                'description',
            ],
        ];

        yield 'With resolved translated field' => [
            ProductStreamDefinition::class,
            'description.invalid',
            [
                'description',
            ],
        ];

        yield 'Without resolved translated field' => [
            ProductStreamDefinition::class,
            'description.invalid',
            [
                'description',
                'invalid',
            ],
            false,
        ];

        yield 'With custom fields without valid field behind' => [
            ProductDefinition::class,
            'customFields.foo.bar',
            [
                'customFields',
            ],
        ];

        yield 'With custom fields with valid field behind' => [
            ProductDefinition::class,
            'customFields.name',
            [
                'customFields',
                'name',
            ],
        ];

        yield 'With json field without valid field behind' => [
            ProductDefinition::class,
            'variantListingConfig.foo.bar',
            [
                'variantListingConfig',
            ],
        ];
    }
}

/**
 * @internal
 */
#[Package('framework')]
class JsonAccessorCategoryExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new ManyToOneAssociationField('extensionParent', 'parent_id', CategoryDefinition::class));
    }

    public function getEntityName(): string
    {
        return CategoryDefinition::ENTITY_NAME;
    }
}
