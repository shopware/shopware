<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Event\ProductStreamAssignment\ProductStreamAssignmentWrittenEvent;

class ProductStreamAssignmentDefinition extends EntityDefinition
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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('product_stream_uuid', 'productStreamUuid', ProductStreamDefinition::class))->setFlags(new Required()),
            (new FkField('product_uuid', 'productUuid', ProductDefinition::class))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('productStream', 'product_stream_uuid', ProductStreamDefinition::class, false),
            new ManyToOneAssociationField('product', 'product_uuid', ProductDefinition::class, false),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own repositories');
    }

    public static function getBasicCollectionClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own collection classes');
    }

    public static function getBasicStructClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own struct classes');
    }

    public static function getWrittenEventClass(): string
    {
        return ProductStreamAssignmentWrittenEvent::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
