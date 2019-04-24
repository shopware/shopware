<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\SalesChannel\SalesChannelCountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\CountryDefinition;

class CountryStateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'country_state';
    }

    public static function getSalesChannelDecorationDefinition(): string
    {
        return SalesChannelCountryStateDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return CountryStateCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CountryStateEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return CountryDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new Required()),

            (new StringField('short_code', 'shortCode'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new TranslatedField('attributes'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false),
            (new TranslationsAssociationField(CountryStateTranslationDefinition::class, 'country_state_id'))->addFlags(new Required()),
            new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_state_id', 'id'),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_state_id', 'id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
