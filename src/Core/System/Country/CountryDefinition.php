<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Country\SalesChannel\SalesChannelCountryDefinition as SalesChannelApiCountryDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CountryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'country';
    }

    public static function getSalesChannelDecorationDefinition(): string
    {
        return SalesChannelApiCountryDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return CountryCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CountryEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('iso', 'iso'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('tax_free', 'taxFree'),
            new BoolField('active', 'active'),
            (new StringField('iso3', 'iso3'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new BoolField('display_state_in_registration', 'displayStateInRegistration'),
            new BoolField('force_state_in_registration', 'forceStateInRegistration'),
            new TranslatedField('attributes'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'country_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('states', CountryStateDefinition::class, 'country_id', 'id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CountryTranslationDefinition::class, 'country_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCountryDefinition::class, 'country_id', 'sales_channel_id'),
        ]);
    }
}
