<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Listing\Definition\ListingSortingDefinition;
use Shopware\Api\Product\Collection\ProductStreamBasicCollection;
use Shopware\Api\Product\Collection\ProductStreamDetailCollection;
use Shopware\Api\Product\Event\ProductStream\ProductStreamWrittenEvent;
use Shopware\Api\Product\Repository\ProductStreamRepository;
use Shopware\Api\Product\Struct\ProductStreamBasicStruct;
use Shopware\Api\Product\Struct\ProductStreamDetailStruct;

class ProductStreamDefinition extends EntityDefinition
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
        return 'product_stream';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('listing_sorting_uuid', 'listingSortingUuid', ListingSortingDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('conditions', 'conditions'),
            new IntField('type', 'type'),
            new LongTextField('description', 'description'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('listingSorting', 'listing_sorting_uuid', ListingSortingDefinition::class, true),
            new ManyToManyAssociationField('productTabs', ProductDefinition::class, ProductStreamTabDefinition::class, false, 'product_stream_uuid', 'product_uuid', 'productTabUuids'),
            new ManyToManyAssociationField('products', ProductDefinition::class, ProductStreamAssignmentDefinition::class, false, 'product_stream_uuid', 'product_uuid', 'productUuids'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductStreamRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductStreamBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductStreamWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductStreamBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductStreamDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductStreamDetailCollection::class;
    }
}
