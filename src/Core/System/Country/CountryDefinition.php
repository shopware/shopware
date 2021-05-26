<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;

class CountryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'country';

    public const TYPE_CUSTOMER_TAX_FREE = 'customer-tax-free';

    public const TYPE_COMPANY_TAX_FREE = 'company-tax-free';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CountryCollection::class;
    }

    public function getEntityClass(): string
    {
        return CountryEntity::class;
    }

    public function getDefaults(): array
    {
        $defaults = [];

        if (Feature::isActive('FEATURE_NEXT_14114')) {
            $defaultTax = [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ];

            $defaults = [
                'vatIdRequired' => false,
                'customerTax' => $defaultTax,
                'companyTax' => $defaultTax,
            ];
        }

        return $defaults;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('iso', 'iso'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new IntField('position', 'position'))->addFlags(new ApiAware()),
            (new BoolField('tax_free', 'taxFree'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new BoolField('shipping_available', 'shippingAvailable'))->addFlags(new ApiAware()),
            (new StringField('iso3', 'iso3'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new BoolField('display_state_in_registration', 'displayStateInRegistration'))->addFlags(new ApiAware()),
            (new BoolField('force_state_in_registration', 'forceStateInRegistration'))->addFlags(new ApiAware()),
            (new BoolField('company_tax_free', 'companyTaxFree'))->addFlags(new ApiAware()),
            (new BoolField('check_vat_id_pattern', 'checkVatIdPattern'))->addFlags(new ApiAware()),
            (new StringField('vat_id_pattern', 'vatIdPattern'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('states', CountryStateDefinition::class, 'country_id', 'id'))
                ->addFlags(new ApiAware(), new CascadeDelete()),

            (new TranslationsAssociationField(CountryTranslationDefinition::class, 'country_id'))
                ->addFlags(new ApiAware(), new Required()),

            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            (new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCountryDefinition::class, 'country_id', 'sales_channel_id')),

            (new OneToManyAssociationField('taxRules', TaxRuleDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField('currencyCountryRoundings', CurrencyCountryRoundingDefinition::class, 'country_id'))
                ->addFlags(new CascadeDelete()),
        ]);

        if (Feature::isActive('FEATURE_NEXT_14114')) {
            $collection->add((new BoolField('vat_id_required', 'vatIdRequired'))->addFlags(new ApiAware()));

            $collection->add((new BoolField('tax_free', 'taxFree'))->addFlags(new ApiAware(), new Deprecated('v6.4.0', 'v6.5.0')));

            $collection->add((new BoolField('company_tax_free', 'companyTaxFree'))->addFlags(new ApiAware(), new Deprecated('v6.4.0', 'v6.5.0')));

            $collection->add((new TaxFreeConfigField('customer_tax', 'customerTax'))->addFlags(new ApiAware()));

            $collection->add((new TaxFreeConfigField('company_tax', 'companyTax'))->addFlags(new ApiAware()));
        }

        return $collection;
    }
}
