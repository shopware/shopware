<?php declare(strict_types=1);

namespace Shopware\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Order\Collection\OrderStateTranslationBasicCollection;
use Shopware\Order\Collection\OrderStateTranslationDetailCollection;
use Shopware\Order\Event\OrderStateTranslation\OrderStateTranslationWrittenEvent;
use Shopware\Order\Repository\OrderStateTranslationRepository;
use Shopware\Order\Struct\OrderStateTranslationBasicStruct;
use Shopware\Order\Struct\OrderStateTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new FkField('order_state_uuid', 'orderStateUuid', OrderStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('description', 'description'))->setFlags(new Required()),
            new ManyToOneAssociationField('orderState', 'order_state_uuid', OrderStateDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
