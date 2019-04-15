<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTag;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class OrderTagDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_tag';
    }

    public static function isVersionAware(): bool
    {
        return true;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('tag_id', 'tagId', TagDefinition::class))->addFlags(new PrimaryKey(), new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false),
        ]);
    }
}
