<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Plugin\PluginDefinition;

class PluginTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'plugin_translation';
    }

    public static function getCollectionClass(): string
    {
        return PluginTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PluginTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return PluginDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new StringField('manufacturer_link', 'manufacturerLink'),
            new StringField('support_link', 'supportLink'),
            new JsonField('changelog', 'changelog'),
        ]);
    }
}
