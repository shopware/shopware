<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderAddress;

use Shopware\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressDetailCollection;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressDetailStruct;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;

class OrderAddressDefinition extends EntityDefinition
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
        return 'order_address';
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

            (new FkField('country_id', 'countryId', \Shopware\System\Country\CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(\Shopware\System\Country\CountryDefinition::class))->setFlags(new Required()),

            new FkField('country_state_id', 'countryStateId', \Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition::class),
            new ReferenceVersionField(\Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition::class),

            (new StringField('salutation', 'salutation'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('street', 'street'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('zipcode', 'zipcode'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('city', 'city'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('company', 'company'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('department', 'department'),
            new StringField('title', 'title'),
            new StringField('vat_id', 'vatId'),
            (new StringField('phone_number', 'phoneNumber'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new ManyToOneAssociationField('country', 'country_id', \Shopware\System\Country\CountryDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('countryState', 'country_state_id', \Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'billing_address_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_address_id', false, 'id'))->setFlags(new RestrictDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderAddressRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderAddressBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderAddressDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderAddressWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderAddressBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderAddressDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderAddressDetailCollection::class;
    }
}
