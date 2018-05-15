<?php declare(strict_types=1);

namespace Shopware\Content\Catalog\Definition;

use Shopware\Content\Catalog\Collection\CatalogBasicCollection;
use Shopware\Content\Catalog\Event\Catalog\CatalogDeletedEvent;
use Shopware\Content\Catalog\Event\Catalog\CatalogWrittenEvent;
use Shopware\Content\Catalog\Repository\CatalogRepository;
use Shopware\Content\Catalog\Struct\CatalogBasicStruct;
use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Content\Category\Definition\CategoryTranslationDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Content\Media\Definition\MediaAlbumDefinition;
use Shopware\Content\Media\Definition\MediaAlbumTranslationDefinition;
use Shopware\Content\Media\Definition\MediaDefinition;
use Shopware\Content\Media\Definition\MediaTranslationDefinition;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;
use Shopware\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;

class CatalogDefinition extends EntityDefinition
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
        return 'catalog';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('mediaAlbum', MediaAlbumDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('mediaAlbumTranslations', MediaAlbumTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('productMedia', \Shopware\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('productStreams', ProductStreamDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('productTranslations', ProductTranslationDefinition::class, 'catalog_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CatalogRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CatalogBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CatalogDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CatalogWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CatalogBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
