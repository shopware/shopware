<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;

class CustomerAddressDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'customer_address';

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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware()),

            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new ApiAware()),
            (new StringField('first_name', 'firstName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('zipcode', 'zipcode'))->addFlags(new ApiAware(), new Required()),
            (new StringField('city', 'city'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('company', 'company'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('street', 'street'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('department', 'department'))->addFlags(new ApiAware()),
            (new StringField('title', 'title'))->addFlags(new ApiAware()),
            (new StringField('phone_number', 'phoneNumber'))->addFlags(new ApiAware()),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new CustomFields())->addFlags(new ApiAware()),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
