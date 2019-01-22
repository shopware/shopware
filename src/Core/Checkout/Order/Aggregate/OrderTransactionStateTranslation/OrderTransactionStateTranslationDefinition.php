<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class OrderTransactionStateTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'order_transaction_state_translation';
    }

    public static function getCollectionClass(): string
    {
        return OrderTransactionStateTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderTransactionStateTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return OrderTransactionStateDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('description', 'description'))->addFlags(new Required()),
        ]);
    }
}
