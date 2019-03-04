<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceRulesJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Tax\TaxDefinition;

class ProductServiceDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_service';
    }

    public static function getCollectionClass(): string
    {
        return ProductServiceCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductServiceEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new FkField('configuration_group_option_id', 'optionId', ConfigurationGroupOptionDefinition::class))->addFlags(new Required()),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new Required()),
            new PriceField('price', 'price'),
            new PriceRulesJsonField('prices', 'prices'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false))->addFlags(new ReverseInherited('services')),
            new ManyToOneAssociationField('option', 'configuration_group_option_id', ConfigurationGroupOptionDefinition::class, true),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, true),
            new AttributesField(),
        ]);
    }
}
