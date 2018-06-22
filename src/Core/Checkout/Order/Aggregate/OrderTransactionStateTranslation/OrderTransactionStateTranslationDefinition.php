<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;

class OrderTransactionStateTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_transaction_state_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('order_transaction_state_id', 'orderTransactionStateId', OrderTransactionStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(OrderTransactionStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('description', 'description'))->setFlags(new Required()),
            new ManyToOneAssociationField('orderTransactionState', 'order_transaction_state_id', OrderTransactionStateDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderTransactionStateTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return OrderTransactionStateTranslationStruct::class;
    }
}
