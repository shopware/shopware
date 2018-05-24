<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService;

use Shopware\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;
use Shopware\Content\Product\Aggregate\ProductService\Event\ProductServiceDeletedEvent;
use Shopware\Content\Product\Aggregate\ProductService\Event\ProductServiceWrittenEvent;
use Shopware\Content\Product\Aggregate\ProductService\Struct\ProductServiceBasicStruct;
use Shopware\Content\Product\ProductDefinition;
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
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\System\Tax\TaxDefinition;

class ProductServiceDefinition extends EntityDefinition
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
        return 'product_service';
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
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(TaxDefinition::class),
            new PriceField('price', 'price'),
            new ContextPricesJsonField('prices', 'prices'),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false))->setFlags(new WriteOnly()),
            new ManyToOneAssociationField('option', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, true),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductServiceRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductServiceBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductServiceDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductServiceWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductServiceBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
