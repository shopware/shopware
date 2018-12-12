<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class OrderStateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_state';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new TranslatedField('description'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('has_mail', 'hasMail'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'order_state_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'order_state_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslationsAssociationField(OrderStateTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderStateCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderStateEntity::class;
    }
}
