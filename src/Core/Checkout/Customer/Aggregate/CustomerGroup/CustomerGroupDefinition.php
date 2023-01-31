<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('customer-order')]
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

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new BoolField('display_gross', 'displayGross'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            // Merchant Registration
            (new BoolField('registration_active', 'registrationActive'))->addFlags(new ApiAware()),
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
