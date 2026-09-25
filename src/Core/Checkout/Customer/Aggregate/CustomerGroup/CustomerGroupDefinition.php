<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('discovery')]
class CustomerGroupDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_group';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerGroupCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerGroupEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return [];
        }

        return [
            'displayGross' => true,
            'priceBasis' => CustomerGroupEntity::PRICE_BASIS_GROSS,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        $displayGross = (new BoolField('display_gross', 'displayGross'))->addFlags(new ApiAware())->setDescription('If boolean value is `true` gross value is displayed else, net value will be displayed to the customer.');
        $priceBasis = (new StringField('price_basis', 'priceBasis'))->addFlags(new ApiAware(), new Since('6.7.16.0'), new Choice([CustomerGroupEntity::PRICE_BASIS_NET, CustomerGroupEntity::PRICE_BASIS_GROSS], strict: true))->setDescription('Defines which stored price value is authoritative for the price calculation. With `net` the stored net value is always used, with `gross` the stored gross value, `null` lets the basis follow the display mode.');

        if (Feature::isActive('v6.8.0.0')) {
            $displayGross->addFlags(new Required());
            $priceBasis->addFlags(new Required())->setDescription('Defines which stored price value is authoritative for the price calculation. With `net` the stored net value is always used, with `gross` the stored gross value.');
        }

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the customer\'s group.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            $displayGross,
            $priceBasis,
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            // Merchant Registration
            (new BoolField('registration_active', 'registrationActive'))->addFlags(new ApiAware())->setDescription('To enable the registration of partner customer group.'),
            (new TranslatedField('registrationTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationIntroduction'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationOnlyCompanyRegistration'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationSeoMetaDescription'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'customer_group_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'customer_group_id', 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(CustomerGroupTranslationDefinition::class, 'customer_group_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('registrationSalesChannels', SalesChannelDefinition::class, CustomerGroupRegistrationSalesChannelDefinition::class, 'customer_group_id', 'sales_channel_id'),
        ]);
    }
}
