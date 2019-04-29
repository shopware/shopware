<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;

class NumberRangeTypeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'number_range_type';
    }

    public static function getCollectionClass(): string
    {
        return NumberRangeTypeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NumberRangeTypeEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('typeName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new BoolField('global', 'global'))->addFlags(new Required()),
            new TranslatedField('attributes'),

            (new OneToManyAssociationField('numberRanges', NumberRangeDefinition::class, 'type_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('numberRangeSalesChannels', NumberRangeSalesChannelDefinition::class, 'number_range_type_id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(NumberRangeTypeTranslationDefinition::class, 'number_range_type_id'))->addFlags(new Required()),
        ]);
    }
}
