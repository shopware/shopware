<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Collection\OrderTransactionStateBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Event\OrderTransactionStateDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Event\OrderTransactionStateWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;

class OrderTransactionStateDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'order_transaction_state';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new IntField('position', 'position'))->setFlags(new Required()),
            (new BoolField('has_mail', 'hasMail'))->setFlags(new Required()),
            (new TranslatedField(new StringField('description', 'description')))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslationsAssociationField('translations', OrderTransactionStateTranslationDefinition::class, 'order_transaction_state_id', false, 'id'))->setFlags(new Required(), new RestrictDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderTransactionStateRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderTransactionStateBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderTransactionStateDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderTransactionStateWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderTransactionStateBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return OrderTransactionStateTranslationDefinition::class;
    }
}
