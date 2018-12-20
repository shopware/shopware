<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class OrderStateTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'order_state_translation';
    }

    public static function getParentDefinitionClass(): string
    {
        return OrderStateDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return OrderStateTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderStateTranslationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('description', 'description'))->setFlags(new Required()),
        ]);
    }
}
