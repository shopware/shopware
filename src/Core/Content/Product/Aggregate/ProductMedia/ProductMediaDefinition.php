<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductMediaDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_media';
    }

    public static function getCollectionClass(): string
    {
        return ProductMediaCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductMediaEntity::class;
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

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new Required()),

            new IntField('position', 'position'),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new ReverseInherited('media')),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', true),
            new AttributesField(),
        ]);
    }
}
