<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation;

use Shopware\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationDetailCollection;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Event\OrderStateTranslationDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Event\OrderStateTranslationWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationRepository;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationDetailStruct;

class OrderStateTranslationDefinition extends EntityDefinition
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
        return 'order_state_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('order_state_id', 'orderStateId', OrderStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(OrderStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('description', 'description'))->setFlags(new Required()),
            new ManyToOneAssociationField('orderState', 'order_state_id', OrderStateDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderStateTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderStateTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderStateTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderStateTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderStateTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderStateTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderStateTranslationDetailCollection::class;
    }
}
