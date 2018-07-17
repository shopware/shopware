<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState;

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
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;

class CountryStateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'country_state';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new Required()),

            (new StringField('short_code', 'shortCode'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            (new TranslationsAssociationField('translations', CountryStateTranslationDefinition::class, 'country_state_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_state_id', false, 'id'),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_state_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_state_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CountryStateCollection::class;
    }

    public static function getStructClass(): string
    {
        return CountryStateStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CountryStateTranslationDefinition::class;
    }
}
