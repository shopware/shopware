<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressDetailCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressDeletedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressWrittenEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct\OrderAddressDetailStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;

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

            (new FkField('country_id', 'countryId', \Shopware\Core\System\Country\CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(\Shopware\Core\System\Country\CountryDefinition::class))->setFlags(new Required()),

            new FkField('country_state_id', 'countryStateId', \Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition::class),
            new ReferenceVersionField(\Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition::class),

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
            (new ManyToOneAssociationField('country', 'country_id', \Shopware\Core\System\Country\CountryDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('countryState', 'country_state_id', \Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
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
