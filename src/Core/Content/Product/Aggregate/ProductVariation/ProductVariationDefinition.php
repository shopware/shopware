<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductVariation;

use Shopware\Content\Product\ProductDefinition;
use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\MappingEntityDefinition;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Content\Product\Aggregate\ProductVariation\Event\ProductVariationDeletedEvent;
use Shopware\Content\Product\Aggregate\ProductVariation\Event\ProductVariationWrittenEvent;

class ProductVariationDefinition extends MappingEntityDefinition
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
        return 'product_variation';
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
        return ProductVariationWrittenEvent::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductVariationDeletedEvent::class;
    }
}
