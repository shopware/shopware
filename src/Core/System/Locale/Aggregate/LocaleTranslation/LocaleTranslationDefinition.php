<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Locale\LocaleDefinition;

class LocaleTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'locale_translation';
    }

    public static function getCollectionClass(): string
    {
        return LocaleTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return LocaleTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return LocaleDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('territory', 'territory'))->addFlags(new Required()),
            new AttributesField(),
        ]);
    }
}
