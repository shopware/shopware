<?php declare(strict_types=1);

namespace Shopware\Api\Test\Entity\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\FieldAccessorBuilder\FieldAccessorBuilderRegistry;
use Shopware\Api\Entity\Dbal\FieldAccessorBuilder\JsonObjectFieldAccessorBuilder;
use Shopware\Api\Entity\Dbal\FieldResolver\FieldResolverRegistry;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;

class EntityDefinitionQueryHelperTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testJsonObjectAccessWithoutAccessorBuilder()
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([])
        );
        $helper->getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            ApplicationContext::createDefaultContext(Defaults::TENANT_ID)
        );
    }

    public function testJsonObjectAccess()
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new JsonObjectFieldAccessorBuilder(),
            ])
        );
        $accessor = $helper->getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            ApplicationContext::createDefaultContext(Defaults::TENANT_ID)
        );

        self::assertEquals('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, "$.gross"))', $accessor);
    }

    public function testNestedJsonObjectAccessor()
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new JsonObjectFieldAccessorBuilder(),
            ])
        );
        $accessor = $helper->getFieldAccessor(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            ApplicationContext::createDefaultContext(Defaults::TENANT_ID)
        );

        self::assertEquals('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, "$.gross.value"))', $accessor);
    }

    public function testGetFieldWithJsonAccessor()
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new JsonObjectFieldAccessorBuilder(),
            ])
        );
        $field = $helper->getField(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName()
        );

        $this->assertInstanceOf(JsonObjectField::class, $field);
    }
}

class JsonObjectTestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'json_object_test';
    }

    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            new JsonObjectField('amount', 'amount'),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return '';
    }

    public static function getBasicCollectionClass(): string
    {
        return '';
    }

    public static function getBasicStructClass(): string
    {
        return '';
    }

    public static function getWrittenEventClass(): string
    {
        return '';
    }

    public static function getDeletedEventClass(): string
    {
        return '';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return '';
    }
}
