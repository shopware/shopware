<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\ORM\Dbal\Exception\FieldAccessorBuilderNotFoundException;
use Shopware\Core\Framework\ORM\Dbal\FieldAccessorBuilder\FieldAccessorBuilderRegistry;
use Shopware\Core\Framework\ORM\Dbal\FieldAccessorBuilder\ObjectFieldAccessorBuilder;
use Shopware\Core\Framework\ORM\Dbal\FieldResolver\FieldResolverRegistry;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\FieldCollection;

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
            Context::createDefaultContext(Defaults::TENANT_ID)
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
            Context::createDefaultContext(Defaults::TENANT_ID)
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
            Context::createDefaultContext(Defaults::TENANT_ID)
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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            new ObjectField('amount', 'amount'),
        ]);
    }
}
