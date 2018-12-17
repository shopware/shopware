<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CatalogField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReverseInherited;

class ProductMediaDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_media';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new Required()),

            new IntField('position', 'position'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false))->setFlags(new ReverseInherited('media')),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, true),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ProductMediaCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductMediaEntity::class;
    }

    public static function getRootDefinition(): ?string
    {
        return MediaDefinition::class;
    }
}
