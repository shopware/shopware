<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Content\Media\Definition\MediaDefinition;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Collection\ProductMediaDetailCollection;
use Shopware\Api\Product\Event\ProductMedia\ProductMediaDeletedEvent;
use Shopware\Api\Product\Event\ProductMedia\ProductMediaWrittenEvent;
use Shopware\Api\Product\Repository\ProductMediaRepository;
use Shopware\Api\Product\Struct\ProductMediaBasicStruct;
use Shopware\Api\Product\Struct\ProductMediaDetailStruct;

class ProductMediaDefinition extends EntityDefinition
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
        return 'product_media';
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
            new CatalogField(),

            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MediaDefinition::class))->setFlags(new Required()),

            (new BoolField('is_cover', 'isCover'))->setFlags(new Required()),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductMediaRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductMediaBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductMediaDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductMediaWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductMediaBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductMediaDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductMediaDetailCollection::class;
    }
}
