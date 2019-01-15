<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CountryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'country';
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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new TranslatedField('name'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('iso', 'iso'))->setFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('shipping_free', 'shippingFree'),
            new BoolField('tax_free', 'taxFree'),
            new BoolField('taxfree_for_vat_id', 'taxfreeForVatId'),
            new BoolField('taxfree_vatid_checked', 'taxfreeVatidChecked'),
            new BoolField('active', 'active'),
            (new StringField('iso3', 'iso3'))->setFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new BoolField('display_state_in_registration', 'displayStateInRegistration'),
            new BoolField('force_state_in_registration', 'forceStateInRegistration'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('states', CountryStateDefinition::class, 'country_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CountryTranslationDefinition::class, 'country_id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCountryDefinition::class, false, 'country_id', 'sales_channel_id'),
        ]);
    }
}
