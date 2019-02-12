<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AttributesTestTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'attribute_test_translation';
    }

    public static function getParentDefinitionClass(): string
    {
        return AttributesTestDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new AttributesField('translated_attributes', 'translatedAttributes'),
        ]);
    }
}
