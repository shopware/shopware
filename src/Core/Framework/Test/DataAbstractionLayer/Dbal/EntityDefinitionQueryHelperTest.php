<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\FieldAccessorBuilderNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ObjectFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class EntityDefinitionQueryHelperTest extends TestCase
{
    public function testJsonObjectAccessWithoutAccessorBuilder(): void
    {
        $this->expectException(FieldAccessorBuilderNotFoundException::class);

        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([])
        );
        $helper->getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            Context::createDefaultContext()
        );
    }

    public function testJsonObjectAccess(): void
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new ObjectFieldAccessorBuilder(),
            ])
        );
        $accessor = $helper->getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            Context::createDefaultContext()
        );

        self::assertEquals('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, "$.gross"))', $accessor);
    }

    public function testNestedJsonObjectAccessor(): void
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new ObjectFieldAccessorBuilder(),
            ])
        );
        $accessor = $helper->getFieldAccessor(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            Context::createDefaultContext()
        );

        self::assertEquals('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, "$.gross.value"))', $accessor);
    }

    public function testGetFieldWithJsonAccessor(): void
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new ObjectFieldAccessorBuilder(),
            ])
        );
        $field = $helper->getField(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName()
        );

        static::assertInstanceOf(ObjectField::class, $field);
    }
}

class JsonObjectTestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'json_object_test';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new ObjectField('amount', 'amount'),
        ]);
    }
}
