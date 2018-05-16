<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductDatasheet;

use Shopware\Content\Product\ProductDefinition;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\MappingEntityDefinition;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Content\Product\Aggregate\ProductDatasheet\Event\ProductDatasheetDeletedEvent;
use Shopware\Content\Product\Aggregate\ProductDatasheet\Event\ProductDatasheetWrittenEvent;

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
