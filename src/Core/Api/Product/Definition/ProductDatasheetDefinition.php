<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\MappingEntityDefinition;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Event\ProductDatasheet\ProductDatasheetDeletedEvent;
use Shopware\Api\Product\Event\ProductDatasheet\ProductDatasheetWrittenEvent;

class ProductDatasheetDefinition extends MappingEntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    public static function getEntityName(): string
    {
        return 'product_datasheet';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        return self::$fields = new FieldCollection([
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(ProductDefinition::class),
            (new FkField('configuration_group_option_id', 'optionId', ConfigurationGroupOptionDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(ConfigurationGroupOptionDefinition::class),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
            new ManyToOneAssociationField('option', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, false),
        ]);
    }

    public static function getWrittenEventClass(): string
    {
        return ProductDatasheetWrittenEvent::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductDatasheetDeletedEvent::class;
    }
}
