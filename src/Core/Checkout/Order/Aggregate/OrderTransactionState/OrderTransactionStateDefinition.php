<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TenantIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;

class OrderTransactionStateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_transaction_state';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            new VersionField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new IntField('position', 'position'))->setFlags(new Required()),
            (new BoolField('has_mail', 'hasMail'))->setFlags(new Required()),
            new TranslatedField('description'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField(OrderTransactionStateTranslationDefinition::class))->setFlags(new Required(), new RestrictDelete()),
            new OneToManyAssociationField('orderTransactions', OrderTransactionDefinition::class, 'order_transaction_state_id', false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderTransactionStateCollection::class;
    }

    public static function getStructClass(): string
    {
        return OrderTransactionStateStruct::class;
    }
}
