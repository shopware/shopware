<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiCriteriaAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityDefinitionQueryHelper::class)]
class EntityDefinitionQueryHelperTest extends TestCase
{
    #[DataProvider('provideTestGetRoot')]
    public function testGetRoot(string $accessor, ?string $expectedRoot): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertSame(
            $expectedRoot,
            EntityDefinitionQueryHelper::getRoot($accessor, $definition)
        );
    }

    #[DataProvider('provideTestGetField')]
    public function testGetField(string $fieldName, bool $resolveTranslated, ?Field $expectedField): void
    {
        $definition = $this->getRegistry()->getByEntityName(ProductDefinition::ENTITY_NAME);
        $actualField = EntityDefinitionQueryHelper::getField($fieldName, $definition, ProductDefinition::ENTITY_NAME, $resolveTranslated);

        if ($expectedField === null) {
            static::assertNull($actualField);

            return;
        }

        static::assertNotNull($actualField);
        static::assertSame(
            $expectedField::class,
            $actualField::class
        );

        static::assertSame(
            $expectedField->getPropertyName(),
            $actualField->getPropertyName()
        );
    }

    #[DataProvider('provideGetAssociatedDefinition')]
    public function testGetAssociatedDefinition(string $accessor, string $expectedEntity): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertSame(
            $expectedEntity,
            EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $accessor)->getEntityName()
        );
    }

    #[DataProvider('provideRemoveExtensionPathSegments')]
    public function testRemoveExtensionPathSegments(string $accessor, string $expected): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');

        static::assertSame($expected, EntityDefinitionQueryHelper::removeExtensionPathSegments($accessor, $definition));
    }

    public static function provideRemoveExtensionPathSegments(): \Generator
    {
        yield 'leading extensions segment is removed' => ['extensions.manufacturer.id', 'manufacturer.id'];
        yield 'nested extensions segment is removed' => ['categories.extensions.name', 'categories.name'];
        yield 'multiple entity extensions segments are removed' => ['extensions.manufacturer.extensions.name', 'manufacturer.name'];
        yield 'substring is kept' => ['customFields.barextensions.id', 'customFields.barextensions.id'];
        yield 'trailing JSON key is kept' => ['product.customFields.extensions', 'product.customFields.extensions'];
        yield 'JSON path is kept after an extension association' => [
            'product.extensions.categories.extensions.customFields.extensions.id',
            'product.categories.customFields.extensions.id',
        ];
        yield 'leading extensions segment before the entity name is removed' => ['extensions.product.customFields.extensions', 'product.customFields.extensions'];
        yield 'accessor without extensions is unchanged' => ['product.name', 'product.name'];
    }

    public static function provideTestGetField(): \Generator
    {
        yield 'unknown field' => [
            'unknown.field',
            true,
            null,
        ];

        yield 'non translated field' => [
            'manufacturerNumber',
            true,
            new StringField('manufacturer_number', 'manufacturerNumber'),
        ];

        yield 'resolve translated on non translated field' => [
            'manufacturerNumber',
            false,
            new StringField('manufacturer_number', 'manufacturerNumber'),
        ];

        yield 'int field' => [
            'manufacturerNumber',
            false,
            new StringField('manufacturer_number', 'manufacturerNumber'),
        ];

        yield 'translated field' => [
            'name',
            false,
            new TranslatedField('name'),
        ];

        yield 'resolve translated field' => [
            'name',
            true,
            new StringField('name', 'name'),
        ];

        yield 'association translated field' => [
            'manufacturer.name',
            false,
            new TranslatedField('name'),
        ];

        yield 'resolve association translated field' => [
            'manufacturer.name',
            true,
            new StringField('name', 'name'),
        ];
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

    public function testEscapeQuotesAValidIdentifier(): void
    {
        static::assertSame('`product`', EntityDefinitionQueryHelper::escape('product'));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideDisallowedIdentifiers(): \Generator
    {
        yield 'backtick' => ['pro`duct'];
        yield 'question mark' => ['pro?duct'];
        yield 'colon' => ['pro:duct'];
        yield 'control character' => ["pro\nduct"];
        yield 'single quote' => ['pro\'duct'];
        yield 'double quote' => ['pro"duct'];
        yield 'double dash comment' => ['pro--duct'];
        yield 'backslash' => ['pro\\duct'];
        yield 'semicolon' => ['pro;duct'];
        yield 'block comment alias t/*' => ['t/*'];
        yield 'block comment close */' => ['a*/b'];
        // malformed UTF-8 makes preg_match('/\p{Cc}/u') return false; the NUL must still be rejected
        yield 'control character behind invalid byte' => ["a\xff\x00b"];
    }

    #[DataProvider('provideDisallowedIdentifiers')]
    public function testEscapeRejectsDisallowedIdentifierChars(string $identifier): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::invalidIdentifier($identifier));

        EntityDefinitionQueryHelper::escape($identifier);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideAllowedIdentifiers(): \Generator
    {
        yield 'plain' => ['product'];
        yield 'snake case storage name' => ['customer_tax'];
        yield 'dashed aggregation name' => ['price-count'];
        yield 'dotted key suffix' => ['my_agg.key'];
        yield 'nested root alias' => ['product.translation.override_1'];
        yield 'single dash is allowed' => ['a-b'];
        // lone slash/star cannot form a comment marker, so API-supplied labels keep working
        yield 'lone slash in label' => ['price/range'];
        yield 'lone star in label' => ['col*'];
    }

    #[DataProvider('provideAllowedIdentifiers')]
    public function testEscapeAcceptsValidIdentifiers(string $identifier): void
    {
        static::assertSame('`' . $identifier . '`', EntityDefinitionQueryHelper::escape($identifier));
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function provideJsonPaths(): \Generator
    {
        yield 'simple segment' => ['.foo', true];
        yield 'nested segments' => ['.foo.bar', true];
        yield 'hyphenated key' => ['.foo-bar', true];
        yield 'quoted dotted key' => ['."foo.bar"', true];
        // lone slash/star are inert inside the quote()-wrapped path, so JSON keys keep working
        yield 'allows lone slash' => ['.a/b', true];
        yield 'allows lone star' => ['.a*b', true];
        // the filter-side half of the advisory chain: */ closes the comment the alias opened
        yield 'rejects comment close */' => ['.a*/b', false];
        yield 'rejects comment open /*' => ['.a/*b', false];
        yield 'rejects control character' => [".a\nb", false];
        // malformed UTF-8 makes preg_match('/\p{Cc}/u') return false; must fail safe to rejected
        yield 'rejects control character behind invalid byte' => [".a\xff\x00b", false];
    }

    #[DataProvider('provideJsonPaths')]
    public function testIsValidJsonPath(string $path, bool $expected): void
    {
        static::assertSame($expected, EntityDefinitionQueryHelper::isValidJsonPath($path));
    }

    public function testFieldRestrictedToAdminApiIsRejectedForStoreApi(): void
    {
        $field = new StringField('name', 'name');

        $this->expectExceptionObject(DataAbstractionLayerException::accessorNotAllowedInApiScope('reference.name'));

        EntityDefinitionQueryHelper::validateFieldIsApiAware($field, 'reference.name', new Context(new SalesChannelApiSource('test')));
    }

    public function testExplicitlyProtectedFieldIsRejectedInSystemScope(): void
    {
        $field = (new StringField('name', 'name'))->removeFlag(ApiAware::class);

        $this->expectExceptionObject(DataAbstractionLayerException::accessorNotAllowedInApiScope('name'));

        EntityDefinitionQueryHelper::validateFieldIsApiAware($field, 'name', Context::createDefaultContext());
    }

    public function testCriteriaAwareFieldCanBeUsedWithoutApiAware(): void
    {
        $this->expectNotToPerformAssertions();

        $field = (new StringField('name', 'name'))->removeFlag(ApiAware::class)->addFlags(new ApiCriteriaAware());

        EntityDefinitionQueryHelper::validateFieldIsApiAware($field, 'name', new Context(new SalesChannelApiSource('test')));
    }

    public function testAdminApiCanUseItsExposedFields(): void
    {
        $this->expectNotToPerformAssertions();

        EntityDefinitionQueryHelper::validateFieldIsApiAware(new StringField('name', 'name'), 'name', new Context(new AdminApiSource('test')));
    }

    public function testPartialFieldSelectionRejectsProtectedAssociation(): void
    {
        $definition = $this->getRegistry()->getByEntityName('product');
        $field = $definition->getField('manufacturer');
        static::assertInstanceOf(Field::class, $field);
        $field->removeFlag(ApiAware::class);

        $this->expectExceptionObject(DataAbstractionLayerException::accessorNotAllowedInApiScope('manufacturer'));

        EntityDefinitionQueryHelper::validateFieldsAccessorIsApiAware($definition, 'manufacturer.name', new Context(new SalesChannelApiSource('test')));
    }

    public function testPartialFieldSelectionLeavesScalarVisibilityToEncoder(): void
    {
        $registry = $this->getRegistry();
        $definition = $registry->getByEntityName('product');
        $field = $registry->getByEntityName('product_manufacturer')->getField('link');
        static::assertInstanceOf(Field::class, $field);
        $field->removeFlag(ApiAware::class);

        EntityDefinitionQueryHelper::validateFieldsAccessorIsApiAware($definition, 'manufacturer.link', new Context(new SalesChannelApiSource('test')));
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
                ProductManufacturerTranslationDefinition::class,
                ProductTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
