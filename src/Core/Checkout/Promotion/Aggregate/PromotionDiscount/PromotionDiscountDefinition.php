<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PromotionDiscountDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'promotion_discount';
    }

    public static function getEntityClass(): string
    {
        return PromotionDiscountEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return PromotionDiscountCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class, 'id'))->addFlags(new Required()),
            (new StringField('type', 'type', 32))->addFlags(new Required()),
            (new FloatField('value', 'value'))->addFlags(new Required()),
            (new BoolField('graduated', 'graduated'))->addFlags(new Required()),
            new IntField('graduation_step', 'graduationStep'),
            new StringField('graduation_order', 'graduationOrder', 32),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id', false),

            (new StringField('apply_towards', 'applyTowards'))->addFlags(new Required()),
            // TODO FK apply_towards_single_group_id, once promotion-group entity is defined
        ]);
    }
}
