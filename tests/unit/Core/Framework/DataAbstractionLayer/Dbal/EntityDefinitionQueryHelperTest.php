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
    }

    #[DataProvider('provideDisallowedIdentifiers')]
    public function testEscapeRejectsDisallowedIdentifierChars(string $identifier): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Backtick, question mark, colon, or control character not allowed in identifier');

        EntityDefinitionQueryHelper::escape($identifier);
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
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }
}
