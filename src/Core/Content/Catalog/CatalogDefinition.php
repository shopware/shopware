<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class CatalogDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'catalog';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mediaAlbum', MediaAlbumDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('mediaAlbumTranslations', MediaAlbumTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('productTranslations', ProductTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete()),
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
}
