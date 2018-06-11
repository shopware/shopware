<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Event\ProductConfiguratorDeletedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Event\ProductConfiguratorWrittenEvent;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Struct\ProductConfiguratorBasicStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\PriceRulesJsonField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\PriceField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;

class ProductConfiguratorDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'product_configurator';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ProductDefinition::class),
            (new FkField('configuration_group_option_id', 'optionId', ConfigurationGroupOptionDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ConfigurationGroupOptionDefinition::class),
            new PriceField('price', 'price'),
            new PriceRulesJsonField('prices', 'prices'),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false))->setFlags(new WriteOnly()),
            new ManyToOneAssociationField('option', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductConfiguratorRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductConfiguratorBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductConfiguratorDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductConfiguratorWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductConfiguratorBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
