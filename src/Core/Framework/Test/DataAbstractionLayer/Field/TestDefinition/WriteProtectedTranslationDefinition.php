<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WriteProtectedTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable_translation';
    }

    public static function getDefinition(): string
    {
        return WriteProtectedTranslatedDefinition::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return WriteProtectedTranslatedDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('protected', 'protected'),
            new StringField('system_protected', 'systemProtected'),
        ]);
    }
}
