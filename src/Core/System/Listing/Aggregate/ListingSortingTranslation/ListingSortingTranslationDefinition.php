<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Listing\ListingSortingDefinition;

class ListingSortingTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'listing_sorting_translation';
    }

    public static function getCollectionClass(): string
    {
        return ListingSortingTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ListingSortingTranslationEntity::class;
    }

    public static function getDefinition(): string
    {
        return ListingSortingDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->setFlags(new Required()),
        ]);
    }
}
