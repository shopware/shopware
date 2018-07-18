<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;

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
            (new TranslatedField(new StringField('description', 'description')))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField('translations', OrderTransactionStateTranslationDefinition::class, 'order_transaction_state_id', false, 'id'))->setFlags(new Required(), new RestrictDelete()),
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

    public static function getTranslationDefinitionClass(): ?string
    {
        return OrderTransactionStateTranslationDefinition::class;
    }
}
