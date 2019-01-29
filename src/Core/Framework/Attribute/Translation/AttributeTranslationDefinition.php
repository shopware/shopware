<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute\Translation;

use Shopware\Core\Framework\Attribute\AttributeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class AttributeTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'attribute_translation';
    }

    public static function getEntityClass(): string
    {
        return AttributeTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return AttributeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
        ]);
    }
}
