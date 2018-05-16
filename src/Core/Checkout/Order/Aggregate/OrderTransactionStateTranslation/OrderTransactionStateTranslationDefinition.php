<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Collection\OrderTransactionStateTranslationBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Event\OrderTransactionStateTranslationDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Event\OrderTransactionStateTranslationWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationRepository;
use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct\OrderTransactionStateTranslationBasicStruct;

class OrderTransactionStateTranslationDefinition extends EntityDefinition
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
        return 'order_transaction_state_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('order_transaction_state_id', 'orderTransactionStateId', OrderTransactionStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(OrderTransactionStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('description', 'description'))->setFlags(new Required()),
            new ManyToOneAssociationField('orderTransactionState', 'order_transaction_state_id', OrderTransactionStateDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderTransactionStateTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderTransactionStateTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderTransactionStateTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderTransactionStateTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderTransactionStateTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
