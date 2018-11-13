<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation\CatalogTranslationDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCatalog\SalesChannelCatalogDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CatalogDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'catalog';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'categoryTranslations'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'mediaTranslations'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ProductManufacturerTranslationDefinition::class, 'productManufacturerTranslations'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ProductTranslationDefinition::class, 'productTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CatalogTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCatalogDefinition::class, false, 'catalog_id', 'sales_channel_id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CatalogCollection::class;
    }

    public static function getStructClass(): string
    {
        return CatalogStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CatalogTranslationDefinition::class;
    }
}
