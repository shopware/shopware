<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamCondition;

use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ProductStreamConditionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_stream_condition';
    }

    public static function getEntityClass(): string
    {
        return ProductStreamConditionEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return ProductStreamConditionCollection::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return ProductStreamDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class))->setFlags(new Required()),
            new ParentFkField(self::class),
            new JsonField('value', 'value'),
            new IntField('position', 'position'),
            new ManyToOneAssociationField('product_stream', 'productStreamId', ProductStreamDefinition::class, false, 'id'),
            new ParentAssociationField(self::class, false),
            new ChildrenAssociationField(self::class),
        ]);
    }
}
