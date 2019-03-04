<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDefinition;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVariation\ProductVariationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ConfigurationGroupOptionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'configuration_group_option';
    }

    public static function getCollectionClass(): string
    {
        return ConfigurationGroupOptionCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ConfigurationGroupOptionEntity::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigurationGroupOptionTranslationDefinition::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return ConfigurationGroupDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('configuration_group_id', 'groupId', ConfigurationGroupDefinition::class))->addFlags(new Required()),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('position'),
            new StringField('color_hex_code', 'colorHexCode'),
            new FkField('media_id', 'mediaId', MediaDefinition::class),
            new TranslatedField('attributes'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, true),
            new ManyToOneAssociationField('group', 'configuration_group_id', ConfigurationGroupDefinition::class, false),
            (new TranslationsAssociationField(ConfigurationGroupOptionTranslationDefinition::class, 'configuration_group_option_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('productConfigurators', ProductConfiguratorDefinition::class, 'configuration_group_option_id', false, 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productServices', ProductServiceDefinition::class, 'configuration_group_option_id', false, 'id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('productDatasheets', ProductDefinition::class, ProductDatasheetDefinition::class, false, 'configuration_group_option_id', 'product_id'))->addFlags(new CascadeDelete(), new ReverseInherited('datasheet')),
            (new ManyToManyAssociationField('productVariations', ProductDefinition::class, ProductVariationDefinition::class, false, 'configuration_group_option_id', 'product_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
