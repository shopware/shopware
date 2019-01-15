<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamCondition\ProductStreamConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ProductStreamDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_stream';
    }

    public static function getCollectionClass(): string
    {
        return ProductStreamCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductStreamEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IntField('priority', 'priority'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            (new ObjectField('payload', 'payload'))->setFlags(new ReadOnly(), new Internal()),
            (new BoolField('invalid', 'invalid'))->setFlags(new ReadOnly()),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new OneToManyAssociationField('conditions', ProductStreamConditionDefinition::class, 'product_stream_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);
    }
}
