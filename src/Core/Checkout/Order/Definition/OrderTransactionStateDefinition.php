<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Checkout\Order\Collection\OrderTransactionStateBasicCollection;
use Shopware\Checkout\Order\Event\OrderTransactionState\OrderTransactionStateDeletedEvent;
use Shopware\Checkout\Order\Event\OrderTransactionState\OrderTransactionStateWrittenEvent;
use Shopware\Checkout\Order\Repository\OrderTransactionStateRepository;
use Shopware\Checkout\Order\Struct\OrderTransactionStateBasicStruct;

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
