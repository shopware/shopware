<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;

#[Package('checkout')]
class CustomerAddressDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_address';

    public const MAX_LENGTH_PHONE_NUMBER = 40;
    public const MAX_LENGTH_FIRST_NAME = 255;
    public const MAX_LENGTH_LAST_NAME = 255;
    public const MAX_LENGTH_TITLE = 100;
    public const MAX_LENGTH_ZIPCODE = 50;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerAddressCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerAddressEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CustomerDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of customer\'s address.'),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of customer.'),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of country\'s state.'),

            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of salutation.'),
            (new StringField('first_name', 'firstName', self::MAX_LENGTH_FIRST_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('First name of the customer.'),
            (new StringField('last_name', 'lastName', self::MAX_LENGTH_LAST_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Last name of the customer.'),
            (new StringField('zipcode', 'zipcode', self::MAX_LENGTH_ZIPCODE))->addFlags(new ApiAware())->setDescription('Postal or zip code of customer\'s address.'),
            (new StringField('city', 'city'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Name of customer\'s city.'),
            (new StringField('company', 'company'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Name of customer\'s company.'),
            (new StringField('street', 'street'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of customer\'s street.'),
            (new StringField('department', 'department'))->addFlags(new ApiAware())->setDescription('Name of customer\'s department.'),
            (new StringField('title', 'title', self::MAX_LENGTH_TITLE))->addFlags(new ApiAware())->setDescription('Titles given to customer like Dr. , Prof., etc'),
            (new StringField('phone_number', 'phoneNumber', self::MAX_LENGTH_PHONE_NUMBER))->addFlags(new ApiAware())->setDescription('Customer\'s phone number.'),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Additional customer\'s address information.'),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Additional customer\'s address information.'),
            (new StringField('hash', 'hash'))->addFlags(new ApiAware(), new Runtime()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
