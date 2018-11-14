<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class EntityDefinitionTest extends TestCase
{
    public function testInheritedFieldNotAdded(): void
    {
        static::assertFalse(SimpleDefintion::getFields()->has('inherited'));
        static::assertNull(SimpleDefintion::getFields()->get('inherited'));
    }

    public function testInheritedFieldAddedIfInheritanceAware(): void
    {
        static::assertTrue(InheritedDefinition::getFields()->has('inherited'));
        static::assertInstanceOf(JsonField::class, InheritedDefinition::getFields()->get('inherited'));
    }

    public function testTranslatedFieldNotAdded(): void
    {
        static::assertFalse(SimpleDefintion::getFields()->has('translated'));
        static::assertNull(SimpleDefintion::getFields()->get('translated'));
    }

    public function testTranslatedFieldAddedIfTranslationAware(): void
    {
        static::assertTrue(TranslatedDefinition::getFields()->has('translated'));
        static::assertInstanceOf(JsonField::class, TranslatedDefinition::getFields()->get('translated'));
    }
}

class SimpleDefintion extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'simple';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
        ]);
    }
}

class TranslatedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'translate';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new TranslationsAssociationField(self::class),
        ]);
    }
}

class InheritedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'inherited';
    }

    public static function isInheritanceAware(): bool
    {
        return true;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
        ]);
    }
}
