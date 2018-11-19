<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;

class CustomerAddressDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'customer_address';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->setFlags(new Required()),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),

            new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class),

            new StringField('salutation', 'salutation'),
            (new StringField('first_name', 'firstName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('zipcode', 'zipcode'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('city', 'city'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('company', 'company'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('street', 'street'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('department', 'department'),
            new StringField('title', 'title'),
            new StringField('vat_id', 'vatId'),
            new StringField('phone_number', 'phoneNumber'),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, true),
            new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, true),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CustomerAddressCollection::class;
    }

    public static function getStructClass(): string
    {
        return CustomerAddressStruct::class;
    }
}
