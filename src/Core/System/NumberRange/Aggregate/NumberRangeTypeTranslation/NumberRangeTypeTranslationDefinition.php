<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;

class NumberRangeTypeTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'number_range_type_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeTypeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeTypeTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return NumberRangeTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('type_name', 'typeName'))->addFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
