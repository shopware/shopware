<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaDefinition;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;

class CountryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'country';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new FkField('country_area_id', 'areaId', CountryAreaDefinition::class),
            new ReferenceVersionField(CountryAreaDefinition::class),

            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('iso', 'iso'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('shipping_free', 'shippingFree'),
            new BoolField('tax_free', 'taxFree'),
            new BoolField('taxfree_for_vat_id', 'taxfreeForVatId'),
            new BoolField('taxfree_vatid_checked', 'taxfreeVatidChecked'),
            new BoolField('active', 'active'),
            (new StringField('iso3', 'iso3'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new BoolField('display_state_in_registration', 'displayStateInRegistration'),
            new BoolField('force_state_in_registration', 'forceStateInRegistration'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('area', 'country_area_id', CountryAreaDefinition::class, false),
            (new OneToManyAssociationField('touchpoints', TouchpointDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('states', CountryStateDefinition::class, 'country_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', CountryTranslationDefinition::class, 'country_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CountryCollection::class;
    }

    public static function getStructClass(): string
    {
        return CountryStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CountryTranslationDefinition::class;
    }
}
