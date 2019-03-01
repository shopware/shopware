<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation;

use Shopware\Core\Content\Navigation\NavigationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class NavigationTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'navigation_translation';
    }

    public static function getCollectionClass(): string
    {
        return NavigationTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NavigationTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return NavigationDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new JsonField('slot_config', 'slotConfig'),
        ]);
    }
}
