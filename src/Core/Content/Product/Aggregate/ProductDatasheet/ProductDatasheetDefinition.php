<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDatasheet;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\MappingEntityDefinition;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(ProductDefinition::class),
            (new FkField('configuration_group_option_id', 'optionId', ConfigurationGroupOptionDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(ConfigurationGroupOptionDefinition::class),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
            new ManyToOneAssociationField('option', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, false),
        ]);
    }

}
