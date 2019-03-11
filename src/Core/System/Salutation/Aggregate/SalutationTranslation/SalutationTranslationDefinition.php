<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;

class SalutationTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'salutation_translation';
    }

    public static function getCollectionClass(): string
    {
        return SalutationTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SalutationTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return SalutationDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
