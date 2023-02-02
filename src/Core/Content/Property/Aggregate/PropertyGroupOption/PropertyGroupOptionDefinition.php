<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOption;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PropertyGroupOptionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'property_group_option';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PropertyGroupOptionCollection::class;
    }

    public function getEntityClass(): string
    {
        return PropertyGroupOptionEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return PropertyGroupOptionHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return PropertyGroupDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('property_group_id', 'groupId', PropertyGroupDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('position'))->addFlags(new ApiAware()),
            (new StringField('color_hex_code', 'colorHexCode'))->addFlags(new ApiAware()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('group', 'property_group_id', PropertyGroupDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(PropertyGroupOptionTranslationDefinition::class, 'property_group_option_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('productConfiguratorSettings', ProductConfiguratorSettingDefinition::class, 'property_group_option_id', 'id'))->addFlags(new RestrictDelete()),
            (new ManyToManyAssociationField('productProperties', ProductDefinition::class, ProductPropertyDefinition::class, 'property_group_option_id', 'product_id'))->addFlags(new CascadeDelete(), new ReverseInherited('properties')),
            (new ManyToManyAssociationField('productOptions', ProductDefinition::class, ProductOptionDefinition::class, 'property_group_option_id', 'product_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
