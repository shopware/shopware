<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\Checkout\Order\Collection\OrderStateTranslationBasicCollection;
use Shopware\Checkout\Order\Collection\OrderStateTranslationDetailCollection;
use Shopware\Checkout\Order\Event\OrderStateTranslation\OrderStateTranslationDeletedEvent;
use Shopware\Checkout\Order\Event\OrderStateTranslation\OrderStateTranslationWrittenEvent;
use Shopware\Checkout\Order\Repository\OrderStateTranslationRepository;
use Shopware\Checkout\Order\Struct\OrderStateTranslationBasicStruct;
use Shopware\Checkout\Order\Struct\OrderStateTranslationDetailStruct;

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
