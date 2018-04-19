<?php declare(strict_types=1);

namespace Shopware\Api\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Mail\Definition\MailDefinition;
use Shopware\Api\Order\Collection\OrderStateBasicCollection;
use Shopware\Api\Order\Collection\OrderStateDetailCollection;
use Shopware\Api\Order\Event\OrderState\OrderStateDeletedEvent;
use Shopware\Api\Order\Event\OrderState\OrderStateWrittenEvent;
use Shopware\Api\Order\Repository\OrderStateRepository;
use Shopware\Api\Order\Struct\OrderStateBasicStruct;
use Shopware\Api\Order\Struct\OrderStateDetailStruct;

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
            (new OneToManyAssociationField('mails', MailDefinition::class, 'order_state_id', false, 'id'))->setFlags(new WriteOnly()),
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
