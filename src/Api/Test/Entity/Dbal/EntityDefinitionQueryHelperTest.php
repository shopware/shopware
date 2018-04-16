<?php

namespace Shopware\Api\Test\Entity\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Context\Struct\ApplicationContext;

class EntityDefinitionQueryHelperTest extends TestCase
{
    public function testJsonObjectAccess()
    {
        $accessor = EntityDefinitionQueryHelper::getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            ApplicationContext::createDefaultContext()
        );

        self::assertEquals('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, "$.gross"))', $accessor);
    }

    public function testNestedJsonObjectAccessor()
    {
        $accessor = EntityDefinitionQueryHelper::getFieldAccessor(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            ApplicationContext::createDefaultContext()
        );

        self::assertEquals('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, "$.gross.value"))', $accessor);
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
            new JsonObjectField('amount', 'amount')
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