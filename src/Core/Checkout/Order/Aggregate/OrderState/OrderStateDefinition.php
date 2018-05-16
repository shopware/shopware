<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderState;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\System\Mail\MailDefinition;
use Shopware\Checkout\Order\Aggregate\OrderState\Collection\OrderStateBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderState\Collection\OrderStateDetailCollection;
use Shopware\Checkout\Order\Aggregate\OrderState\Event\OrderStateDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderState\Event\OrderStateWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderState\OrderStateRepository;
use Shopware\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderState\Struct\OrderStateDetailStruct;

class OrderStateDefinition extends EntityDefinition
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
        return 'order_state';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new TranslatedField(new StringField('description', 'description')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('has_mail', 'hasMail'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('mails', \Shopware\System\Mail\MailDefinition::class, 'order_state_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'order_state_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'order_state_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new TranslationsAssociationField('translations', OrderStateTranslationDefinition::class, 'order_state_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderStateRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderStateBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderStateDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderStateWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderStateBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return OrderStateTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return OrderStateDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderStateDetailCollection::class;
    }
}
