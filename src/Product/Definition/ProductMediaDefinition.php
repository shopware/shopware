<?php declare(strict_types=1);

namespace Shopware\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Media\Definition\MediaDefinition;
use Shopware\Product\Collection\ProductMediaBasicCollection;
use Shopware\Product\Collection\ProductMediaDetailCollection;
use Shopware\Product\Event\ProductMedia\ProductMediaWrittenEvent;
use Shopware\Product\Repository\ProductMediaRepository;
use Shopware\Product\Struct\ProductMediaBasicStruct;
use Shopware\Product\Struct\ProductMediaDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('product_uuid', 'productUuid', ProductDefinition::class))->setFlags(new Required()),
            (new FkField('media_uuid', 'mediaUuid', MediaDefinition::class))->setFlags(new Required()),
            (new BoolField('is_cover', 'isCover'))->setFlags(new Required()),
            new IntField('position', 'position'),
            new StringField('parent_uuid', 'parentUuid'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('product', 'product_uuid', ProductDefinition::class, false),
            new ManyToOneAssociationField('media', 'media_uuid', MediaDefinition::class, true),
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
