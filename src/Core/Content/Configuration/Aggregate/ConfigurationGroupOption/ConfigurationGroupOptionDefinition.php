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
use Shopware\Core\Framework\ORM\EntityDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;


class ConfigurationGroupOptionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'configuration_group_option';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('configuration_group_id', 'groupId', ConfigurationGroupDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ConfigurationGroupDefinition::class),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new StringField('color_hex_code', 'colorHexCode'),
            new FkField('media_id', 'mediaId', MediaDefinition::class),
            new ReferenceVersionField(MediaDefinition::class),
            new ManyToOneAssociationField('group', 'configuration_group_id', ConfigurationGroupDefinition::class, true),
            (new TranslationsAssociationField('translations', ConfigurationGroupOptionTranslationDefinition::class, 'configuration_group_option_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('productConfigurators', ProductConfiguratorDefinition::class, 'configuration_option_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productServices', ProductServiceDefinition::class, 'configuration_option_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('productDatasheets', ProductDefinition::class, ProductDatasheetDefinition::class, false, 'configuration_group_option_id', 'product_id'))->setFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('productVariations', ProductDefinition::class, ProductVariationDefinition::class, false, 'configuration_group_option_id', 'product_id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ConfigurationGroupOptionCollection::class;
    }

    public static function getStructClass(): string
    {
        return ConfigurationGroupOptionStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigurationGroupOptionTranslationDefinition::class;
    }
}
