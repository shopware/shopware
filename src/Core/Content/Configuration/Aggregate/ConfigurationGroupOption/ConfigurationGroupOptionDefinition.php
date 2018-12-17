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
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReverseInherited;

class ConfigurationGroupOptionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'configuration_group_option';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('configuration_group_id', 'groupId', ConfigurationGroupDefinition::class))->setFlags(new Required()),
            new TranslatedField('name'),
            new StringField('color_hex_code', 'colorHexCode'),
            new FkField('media_id', 'mediaId', MediaDefinition::class),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('group', 'configuration_group_id', ConfigurationGroupDefinition::class, true),
            (new TranslationsAssociationField(ConfigurationGroupOptionTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('productConfigurators', ProductConfiguratorDefinition::class, 'configuration_group_option_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productServices', ProductServiceDefinition::class, 'configuration_group_option_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('productDatasheets', ProductDefinition::class, ProductDatasheetDefinition::class, false, 'configuration_group_option_id', 'product_id'))->setFlags(new CascadeDelete(), new ReverseInherited('datasheet')),
            (new ManyToManyAssociationField('productVariations', ProductDefinition::class, ProductVariationDefinition::class, false, 'configuration_group_option_id', 'product_id'))->setFlags(new CascadeDelete()),
        ]);
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

    public static function getRootDefinition(): ?string
    {
        return ConfigurationGroupDefinition::class;
    }
}
