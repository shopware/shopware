<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class CatalogTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'catalog_translation';
    }

    public static function getCollectionClass(): string
    {
        return CatalogTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CatalogTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return CatalogDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
