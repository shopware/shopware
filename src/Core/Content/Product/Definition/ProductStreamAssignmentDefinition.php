<?php declare(strict_types=1);

namespace Shopware\Content\Product\Definition;

use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\MappingEntityDefinition;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Content\Product\Event\ProductStreamAssignment\ProductStreamAssignmentDeletedEvent;
use Shopware\Content\Product\Event\ProductStreamAssignment\ProductStreamAssignmentWrittenEvent;

class ProductStreamAssignmentDefinition extends MappingEntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    public static function getEntityName(): string
    {
        return 'product_stream_assignment';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        return self::$fields = new FieldCollection([
            (new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ProductStreamDefinition::class))->setFlags(new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->setFlags(new Required()),

            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class, false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false),
        ]);
    }

    public static function getWrittenEventClass(): string
    {
        return ProductStreamAssignmentWrittenEvent::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductStreamAssignmentDeletedEvent::class;
    }
}
