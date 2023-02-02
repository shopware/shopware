<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;

class SalutationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'salutation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalutationCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalutationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('salutation_key', 'salutationKey'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('displayName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('letterName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new TranslationsAssociationField(SalutationTranslationDefinition::class, 'salutation_id'))->addFlags(new Required()),

            // Reverse Associations, not available in sales-channel-api
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
