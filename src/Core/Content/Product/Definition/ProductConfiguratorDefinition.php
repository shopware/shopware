<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\ContextPricesJsonField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\PriceField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Content\Product\Collection\ProductConfiguratorBasicCollection;
use Shopware\Content\Product\Event\ProductConfigurator\ProductConfiguratorDeletedEvent;
use Shopware\Content\Product\Event\ProductConfigurator\ProductConfiguratorWrittenEvent;
use Shopware\Content\Product\Repository\ProductConfiguratorRepository;
use Shopware\Content\Product\Struct\ProductConfiguratorBasicStruct;

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
            new ContextPricesJsonField('prices', 'prices'),
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
