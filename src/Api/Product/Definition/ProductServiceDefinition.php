<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\ContextPricesJsonField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\PriceField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Product\Collection\ProductServiceBasicCollection;
use Shopware\Api\Product\Event\ProductService\ProductServiceDeletedEvent;
use Shopware\Api\Product\Event\ProductService\ProductServiceWrittenEvent;
use Shopware\Api\Product\Repository\ProductServiceRepository;
use Shopware\Api\Product\Struct\ProductServiceBasicStruct;
use Shopware\Api\Tax\Definition\TaxDefinition;

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
