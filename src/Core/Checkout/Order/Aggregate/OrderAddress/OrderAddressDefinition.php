<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TenantIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;

class OrderAddressDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_address';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new Required()),

            new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class),
            new ReferenceVersionField(CountryStateDefinition::class),

            new StringField('salutation', 'salutation'),
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
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'billing_address_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_order_address_id', false, 'id'))->setFlags(new RestrictDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderAddressCollection::class;
    }

    public static function getStructClass(): string
    {
        return OrderAddressStruct::class;
    }
}
