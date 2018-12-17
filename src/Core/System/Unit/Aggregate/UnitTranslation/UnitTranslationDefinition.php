<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Unit\UnitDefinition;

class UnitTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'unit_translation';
    }

    public static function getCollectionClass(): string
    {
        return UnitTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return UnitTranslationEntity::class;
    }

    public static function getDefinition(): string
    {
        return UnitDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('short_code', 'shortCode'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
        ]);
    }
}
